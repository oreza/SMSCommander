<?php
/**
 * Phone similator using Tropo API, used to similate a phone SMS send and recieve functionality. 
 * You can have as many phone numbers Tropo allows to send and receive messages. 
 * Developed for testing purpose!
 * 
 * PHP 5.3 +
 *
 * LICENSE: This source file is protected by explicit copy right please contact
 *
 * @category   
 * @package    SMS
 * @author     Ovais Reza <ovaisreza@netropy.ca>
 * @copyright  2011-2012 Netropy
 * @license    http://netropy.ca/license.txt PHP License 3.0
 * @version    CVS: $Id:$
 * @see        
 * @since      File available since Release 1.0.0
 * @deprecated File deprecated in Release x.x.x
 */

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'core/smscommanderphonelist.class.php');
require_once('phone_out.class.php');


class SMSCommander
{
	const PROFILE_DEV 		= 'DEV';
	const PROFILE_PROD		= 'PROD';
	//
    private $filename 		= 'in.txt';
    private $prompt 		= 'command>';
    private $phone_out  	= null;
    private $profile		= null;
	
    private $commands   	= array( 
									'quit'   	=> "Type 'quit', 'exit' or press Ctrl+C to exit!",
									'exit'   	=> "",
									'list'   	=> "Type 'list' to get this message",
									'.'      	=> "Type '.'  to skip message!",
									'get'    	=> "Type 'get'  to retrieve next message from queue!",
									'send'   	=> "Type 'send <reply>' to send reply back!",
									'show'   	=> "Type 'show' to see current message!",
									'req'    	=> "Type 'req <keyword>' to send info message!",
									'pp'		=> "Type 'pp' to select Production profile!",
									'pd'		=> "Type 'pd' to select Development profile!",
									'du'		=> "Type 'du' to see test dump, for debugging!",
                                );
                         
    
    
    function __construct()
    {
		$this->profile 		= static::PROFILE_DEV;
		$this->phone_out 	= new PhoneOut($this->profile);  
    }
    
	private function selectProfile($profile)
	{
		
		if ($profile == static::PROFILE_DEV)
		{
			$this->profile =  static::PROFILE_DEV; 
		}
		else
		{	
			$this->profile =  static::PROFILE_PROD; 
		}
		
		$this->phone_out = new PhoneOut($this->profile);
	}
	
    public function getRandomPhone()
    {
		$list = SMSCommanderPhoneList::getPhoneList();
		if (is_array($list))
		{
			$i = array_rand($list);
			return $list[$i];	
		}
		
       return null;
    }
    
    private function startsWith($haystack, $needle)
	{
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}
    
    private function help()
    {
        echo "\n";
        foreach($this->commands as $key => $value)
        {
            if ($value != "") 
            {
                echo $value . "\n";
            }
        }
        echo "\n";
    }
    
    private function doExit()
    {
        $this->echoMessage("Exiting - Thank you for using SMS Commander Console");
        exit();
    }
    
    private function getMessage()
    {
        $xml = null;
        
        if ($this->phone_out != null)
        {
            $message = $this->phone_out->getMessage();
            
            if ($message !== null) 
            {
                $xml = simplexml_load_string($message);
				                
                if ($xml === false) 
                {
                    l("Invalid xml given, xml is: $xml", true);
                    return null;
                }
            }
        }
        
        return $xml;
    }
    
    private function updateMessage($id)
    {
        if ($this->phone_out != null)
        {
            $this->phone_out->updateMessage($id);
        }
    }
    
    private function showMessage($dest, $data) 
    {
        echo "\nRetrieving message! \n";    
        echo "For mobile number: " . $dest . "\n";
        echo "Message content are:\n( " . $data . " )\n\n";
    }
    
    private function sendReply($reply, $dest, $src, $ready, $regex = null) 
    {
        if ($ready) 
        {

           if ($this->isCommandValid($reply) == false) 
           {
                $this->echoMessage("Invalid reply ($reply)!");
                return false;
           } 
           
           $this->echoMessage("Sending reply ($reply) to ($dest) for SMS message");
           $this->phone_out->sendIncommingMessange($dest, $src, $reply );
           return true;
        }
        
        return false;
    }
    
    private function validKeyword($x) 
	{
		$matches = null;
		$m = preg_match('/^cw([0-9]{1,2})$/', strtolower($x), $matches);
		$hole_number = (int) $matches[1];
		$valid = (($m != 0) && ($m != false) &&($hole_number >= 1) && ($hole_number <= 18));
        //echo "\nIs valid keyworkd $x and m is $m" . var_export($valid, true) . "\n";
		return $valid;
	}
    
    private function isCommandValid($command) 
    {
        $ret = false;
        
        $cmd = trim(strtoupper($command));
        
        if (is_numeric($cmd)) 
        { 
            $match = preg_match('/\d{1,2}/', trim($command));
        
            $ret = ($match !== 0 && $match !== false); 
        } 
        else
        {
            if (array_key_exists(strtolower($cmd), $this->commands))
            {
                $ret = true;
            }
            
            if (array_key_exists($cmd, array_flip(array('STOP','ARRET', 'YES', 'JOIN', 'OUI', 'HELP','AIDE','INFO')))) 
            {
                $ret = true;
            }
            
            if ($this->startsWith($cmd, 'SEND'))
            {
                return true;
            }
            
            if ($this->startsWith($cmd, 'CW'))
            {
                $ret = $this->validKeyword($cmd);
            }
        }
        //echo "Does not eixsts ($cmd) and its value ($ret) " . var_export($ret, true) . "\n";
        return $ret;
   }
    
    private function echoMessage($message) 
    {
        echo "\n" . $message . "\n";
    }
    
    private function processCommand() 
    {
        $done       = false;
        $command    = '';
        $msg        = null;
        $ready      = false;
        
        $fp         = fopen('php://stdin', 'r');

        $this->echoMessage("Welcome to SMS Commander! Type 'list' to see list of commands. Type a command .....");
        echo $this->prompt; 
        while (!$done) 
        {
            $update_message = false;

            $next_line = fgets($fp, 1024); 

            $command = trim($next_line);
                   
            
                   
            if (!empty($command) && $command != PHP_EOL) 
            {
                if ($this->isCommandValid($command) == false)
                {
                    $this->echoMessage("Unknown command ($command), type list for list of commands!");
                } 
                else
                {
                    $command = strtolower($command);
                }
                
                if ($command == 'get')
                {

                    $msg = $this->getMessage();   
                    
                    if ($msg !== null)
                    {
                        $ready = true;
                        $this->showMessage($msg->dest, $msg->data);
                    } 
                    else
                    {
                        $this->echoMessage("No messages in the queue! \n");
                    }
                    
                }
                
                if ($this->startsWith( $command, 'req' ))
                {
                    $reply = trim(substr( $command, 3 ));
                    $dest = $this->getRandomPhone();
                    $this->sendReply( $reply, $dest, '16472154006' , true);
                }
                
                if ($this->startsWith( $command, 'send' ) && ( $msg != null ) && ( $ready ))
                {   
                    $reply = trim(substr( $command, 4 ));
                    
                    $update_message = $this->sendReply( $reply, $msg->dest, $msg->src, $ready );
                    
                    if ($update_message) 
                    {
                        $this->echoMessage($msg->data);
                    }
                    
                    $ready = false;
                }

                if (($command == '.') && ($msg != null))
                {
                    $m = substr($msg->data,0, 19) . "...";
                    $this->echoMessage("Message ( $m ) skipped for mobile # ($msg->dest), no replies sent!");
                    $update_message = true;
                }
                
                if ($command == 'show')
                {
                    if ($msg != null)
                    {
                        $this->showMessage($msg->dest, $msg->data);
                    } 
                    else
                    {
                        $this->echoMessage("No message selected, type get to retrieve message from the queue!");
                    }
                }
                
                if ($command == 'list')
                {
                    $this->help();
                }
				
				if ($command == 'pp')
				{
					$this->selectProfile(static::PROFILE_PROD);
					$this->echoMessage("{$this->profile} profile selected!");
				}
				
				if ($command == 'pd')
				{
					$this->selectProfile(static::PROFILE_DEV);
					$this->echoMessage("{$this->profile} profile selected!");
				}
				
				if ($command == 'du')
				{
					global $db;
					print_r($db);
                }
				
                if ($command == 'quit' || $command == 'exit') 
                {
                    $this->doExit();
                }
 
            }
            
            if (($update_message) && ($msg != null))
            {
                //delete message file
                $this->updateMessage($msg->id);
                $msg = null;
            }

            echo $this->prompt;
        }
    }
    
    public function main() 
    {
        $this->processCommand();
    }
    
    public function test($mobile_number)
    {
        if ($this->startsWith($mobile_number, '*'))
        {
            $i = strpos($mobile_number, '+');
            return $mobile_number = substr($mobile_number, $i );
        }
        return null;
    }
}


$x = new SMSCommander();
$x->main();
