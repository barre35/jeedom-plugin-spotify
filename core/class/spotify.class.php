<?php

require_once dirname(__FILE__).'/../../../../core/php/core.inc.php';

require_once dirname(__FILE__).'/../../3rdparty/SpotifyWebAPI/Session.php';
require_once dirname(__FILE__).'/../../3rdparty/SpotifyWebAPI/Request.php';
require_once dirname(__FILE__).'/../../3rdparty/SpotifyWebAPI/SpotifyWebAPI.php';
require_once dirname(__FILE__).'/../../3rdparty/SpotifyWebAPI/SpotifyWebAPIException.php';
require_once dirname(__FILE__).'/../../3rdparty/SpotifyWebAPI/SpotifyWebAPIAuthException.php';

if (config::byKey( 'api', 'spotify') == '') {
	config::save('api', config::genKey(), 'spotify');
}

class CastMessage {

	public $protocolversion = 0; // CASTV2_1_0 - It's always this
	public $source_id; // Source ID String
	public $receiver_id; // Receiver ID String
	public $urnnamespace; // Namespace
	public $payloadtype = 0; // PayloadType String=0 Binary = 1
	public $payloadutf8; // Payload

  	private function hex_dump($data)
    {
      static $from = '';
      static $to = '';

      static $width = 16; # number of bytes per line

      static $pad = '.'; # padding for non-visible characters

      if ($from==='')
      {
        for ($i=0; $i<=0xFF; $i++)
        {
          $from .= chr($i);
          $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
        }
      }

      $hex = str_split(bin2hex($data), $width*2);
      $chars = str_split(strtr($data, $from, $to), $width);

      $offset = 0;
      foreach ($hex as $i => $line)
      {
        log::add('spotify', 'debug', sprintf('%6X',$offset).' : '.implode(' ', str_split($line,2)) . ' [' . $chars[$i] . ']' );
        $offset += $width;
      }
      
      return $txt;
    }
  
  	public function decode( $binstring, $debug = 1 ) {
      
      	if ($debug == 1) log::add('spotify', 'debug', '    --- DECODE --- BEGIN ---');
      
      	$index = 0;
    	$size = strlen($binstring);
      
      	if ($debug == 1) $this->hex_dump( $binstring);
        
      	$length = hex2bin( base_convert(
          	( base_convert( bin2hex( substr( $binstring, $index++, 1) ), 16, 10 ) << 24 )          
         + 	( base_convert( bin2hex( substr( $binstring, $index++, 1) ), 16, 10 ) << 16 )          
         + 	( base_convert( bin2hex( substr( $binstring, $index++, 1) ), 16, 10 ) << 8 )          
         +	( base_convert( bin2hex( substr( $binstring, $index++, 1) ), 16, 10 ) )
        , 10, 16 ) );
      
      	if ($debug == 1) $this->hex_dump( $length);
      	
      	while ( $index < $size ) {
          
          $next = base_convert( bin2hex( substr( $binstring, $index++, 1) ), 16, 10 );
          $field = base_convert( ( ( $next & 0b11111000 ) >> 3 ), 10, 2 );         
          $type = base_convert( ( $next & 0b111 ), 10, 2 );         

          if( $field == "1" && $type == "0" ) {

              $this->protocolversion = $this->binToVarint(substr( $binstring, $index++, 1));              
              if ($debug == 1) log::add('spotify', 'debug', '    --- PROTOCOL = "' . $this->protocolversion . '" ---');  
              
          } else if( $field == "10" && $type == "10" ) {

			  $l_source_id = ord( substr( $binstring, $index++, 1) );                       
              $this->source_id = substr( $binstring, $index, $l_source_id); 
              $index+=$l_source_id;
              if ($debug == 1) log::add('spotify', 'debug', '    --- SOURCE ID = "' . $this->source_id . '" ---');  
              
          } else if( $field == "11" && $type == "10" ) {

              $l_receiver_id = ord( substr( $binstring, $index++, 1) );                       
              $this->receiver_id = substr( $binstring, $index, $l_receiver_id); 
              $index+=$l_receiver_id;
              if ($debug == 1) log::add('spotify', 'debug', '    --- RECEIVER ID = "' . $this->receiver_id . '" ---');  
              
          } else if( $field == "100" && $type == "10" ) {

			  $l_urnnamespace = ord( substr( $binstring, $index++, 1) );                       
              $this->urnnamespace = substr( $binstring, $index, $l_urnnamespace); 
              $index+=$l_urnnamespace;
              if ($debug == 1) log::add('spotify', 'debug', '    --- URNNAMESPACE = "' . $this->urnnamespace . '" ---');  
              
          } else if( $field == "101" && $type == "0" ) {

			  $this->payloadtype = $this->binToVarint(substr( $binstring, $index++, 1));              
              if ($debug == 1) log::add('spotify', 'debug', '    --- PAYLOADTYPE = "' . $this->payloadtype . '" ---');
              
          } else if( $field == "110" && $type == "10" ) {

			  $l_payloadutf8 = ord( substr( $binstring, $index++, 1) );      
              if( $l_payloadutf8 > 128 ) {
                $l_payloadutf8 = $l_payloadutf8 - 128 + ord( substr( $binstring, $index++, 1) ) * 128;
              }
              if ($debug == 1) log::add('spotify', 'debug', '    --- PAYLOADLENGTH = "' . $l_payloadutf8 . '" ---');                                              	
              $this->payloadutf8 = substr( $binstring, $index, $l_payloadutf8); 
              $index+=$l_payloadutf8;
              if ($debug == 1) log::add('spotify', 'debug', '    --- PAYLOADUTF8 = "' . $this->payloadutf8 . '" ---');
              
          } else {
            
          	  if ($debug == 1) log::add('spotify', 'debug', '    ??? FIELD = ' . $field . ' ---');	
          	  if ($debug == 1) log::add('spotify', 'debug', '    ??? TYPE = ' . $type . ' ---');
          
          }
          
    	}
      
      	if ($debug == 1) log::add('spotify', 'debug', '    --- DECODE --- END ---');
      
    }
  
	public function encode( $debug = 0 ) {
      
        if ($debug == 1) log::add('spotify', 'debug', '    --- ENCODE --- BEGIN ---');
      
		$r = "";
	
        // Protocol version
      	if ($debug == 1) log::add('spotify', 'debug', '    --- PROTOCOL = "' . $this->protocolversion . '" ---');  
		$r = "00001"; // Field Number 1
		$r .= "000"; // Int
		$r .= $this->varintToBin($this->protocolversion);
              
		// Source id
      	if ($debug == 1) log::add('spotify', 'debug', '    --- SOURCE ID = "' . $this->source_id . '" ---');
    	$r .= "00010"; // Field Number 2
		$r .= "010"; // String
		$r .= $this->stringToBin($this->source_id);

		// Receiver id
		if ($debug == 1) log::add('spotify', 'debug', '    --- RECEIVER ID = "' . $this->receiver_id . '" ---');  
      	$r .= "00011"; // Field Number 3
		$r .= "010"; // String
		$r .= $this->stringToBin($this->receiver_id);

		// Namespace
      	if ($debug == 1) log::add('spotify', 'debug', '    --- URNNAMESPACE = "' . $this->urnnamespace . '" ---');  
		$r .= "00100"; // Field Number 4
		$r .= "010"; // String
		$r .= $this->stringToBin($this->urnnamespace);

		// Payload type
		if ($debug == 1) log::add('spotify', 'debug', '    --- PAYLOADTYPE = "' . $this->payloadtype . '" ---');
      	$r .= "00101"; // Field Number 5
		$r .= "000"; // VarInt
		$r .= $this->varintToBin($this->payloadtype);
      
		// Payload utf8
		if ($debug == 1) log::add('spotify', 'debug', '    --- PAYLOADUTF8 = "' . $this->payloadutf8 . '" ---');
      	$r .= "00110"; // Field Number 6
		$r .= "010"; // String
		$r .= $this->stringToBin($this->payloadutf8);
		
		// Ignore payload_binary field 7 as never used

		// Now convert it to a binary packet
		$hexstring = "";
		for ( $i=0; $i < strlen($r); $i=$i+8 ) {
			$thischunk = substr($r,$i,8);
			$hx = dechex(bindec($thischunk));
			if (strlen($hx) == 1) { $hx = "0" . $hx; }
			$hexstring .= $hx;
		}
		$l = strlen($hexstring) / 2;
      	$l = dechex($l);
		while (strlen($l) < 8) { $l = "0" . $l; }
		$hexstring = $l . $hexstring;
        $ret = hex2bin($hexstring);
      	
      	if ($debug == 1) $this->hex_dump( $ret);
      
      	if ($debug == 1) log::add('spotify', 'debug', '    --- ENCODE --- END ---');
      
		return $ret;
      
	}

	private function binToVarint($inval) {
	
      return ( ( base_convert( substr( base_convert( $inval, 2, 16), 2, 2), 16, 10) & 0b1111111 ) * 128 ) + ( base_convert( substr( base_convert( $inval, 2, 16), 0, 2), 16, 10) & 0b1111111 );
      
    }
    
  	private function varintToBin($inval) {
		// Convert an integer to a binary varint
		// A variant is returned least significant part first.
		// Number is represented in 7 bit portions. The 8th (MSB) of a byte represents if there
		// is a following byte.
		$r = array();
		while ($inval / 128 > 1) {
			$thisval = ($inval - ($inval % 128)) / 128;
			array_push($r, $thisval);
			$inval = $inval - ($thisval * 128);
		}
		array_push($r, $inval);
		$r = array_reverse($r);
		$binaryString = "";
		$c = 1;
		foreach ($r as $num) {
			if ($c != sizeof($r)) { $num = $num + 128; }
			$tv = decbin($num);
			while (strlen($tv) < 8) { $tv = "0" . $tv; }
			$c++;
			$binaryString .= $tv;
		}
		return $binaryString;
	}

	private function stringToBin($string) {
		// Convert a string to a Binary string
		// First the length (note this is a binary varint)
		$l = strlen($string);
		$ret = "";
		$ret = $this->varintToBin($l);
		for ($i = 0; $i < $l; $i++) {
			$n = decbin(ord(substr($string,$i,1)));
			while (strlen($n) < 8) { $n = "0" . $n; }
			$ret .= $n;
		}
		return $ret;
	}

}

class spotify extends eqLogic {

  	/*************** Attributs ***************/

  	public static $_widgetPossibility = array('custom' => true);
    
    public static function templateWidget(){
		$return = array('info' => array('string' => array()));
		$return['info']['string']['Image'] = array(
			'template' => 'tmplSpotifyImage'
		);
      	$return['action']['select']['Devices'] = array(
			'template' => 'tmplSpotifyDevices'
		);
		return $return;
	}
  
  	/************* Static methods ************/

  	public static function deamon_info() {
    
    	//log::add('spotify', 'debug', '--- DAEMON INFO ---');
      
      	$return = array();
      
		$return['log'] = 'spotify_daemon';
      	$return['launchable'] = 'ok';      	
      
        $pid = trim( shell_exec ('ps ax | grep "spotify.js" | grep -v "grep" | wc -l') );
    
      	if ($pid != '' && $pid != '0') {
          	// log::add('spotify', 'debug', '--- DAEMON PID = '.$pid.' ---');
      		$return['state'] = 'ok';
    	} else  {
      		// log::add('spotify', 'debug', '--- DAEMON NOT LAUNCH ---');
			$return['state'] = 'nok';			
        }
      
      	return $return;
      
  	}

  	public static function deamon_start() {
      
        log::add('spotify', 'debug', '--- DAEMON START ---');
      
      	$deamon_info = self::deamon_info();
        log::add('spotify', 'debug', '--- DAEMON info '.json_encode($deamon_info).'---');
      
      	if ($deamon_info['state'] == 'nok') {
    
          $protocol = config::byKey('protocol', 'spotify');
          log::add('spotify', 'debug', '--- PROTOCOL = '.$protocol.' ---');
          
       	  $key = jeedom::getApiKey('spotify');
		  log::add('spotify', 'debug', '--- KEY = '.$key.' ---');
      
          if( $protocol == 'HTTP' ) {
            $net = network::getNetworkAccess('internal');
            log::add('spotify', 'debug', '--- NET = '.$net.' ---');
          } else {
            $net = network::getNetworkAccess('external');
            log::add('spotify', 'debug', '--- NET = '.$net.' ---');
          }  
            
    	  $url = $net . '/plugins/spotify/core/ajax/spotify.ajax.php?action=account&api=' . $key;
      	  log::add('spotify', 'debug', '--- URL = '.$url.' ---');
      		
          $loglevel = log::getLogLevel('spotify');
          log::add('spotify', 'debug', '--- LOG LEVEL = '.$loglevel.' ---');
      	
    	  //if( $loglevel <= 200 ) {
            $log = log::getPathToLog('spotify_daemon');
		  //} else {
      	  //  $log = '/dev/null';
          //}
          log::add('spotify', 'debug', '--- LOG = '.$log.' ---');
          
	      //$cmd = 'sudo nice -n 19 nodejs "/var/www/html/plugins/spotify/ressources/spotify.js" "' . $url . '" "true" "' . $protocol . '" > "' . $log . '" 2>&1 &';
		  $cmd = 'sudo nice -n 19 nodejs "/var/www/html/plugins/spotify/ressources/spotify.js" "' . $url . '" "' . $loglevel . '" "' . $protocol . '" > "' . $log . '" 2>&1 &';
		  log::add('spotify', 'debug', '--- CMD = '.$cmd.' ---');
      		
    	  $result = exec($cmd);
      	  log::add('spotify', 'debug', '--- RESULT = '.$result.' ---');
                 
      	  if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
	   		log::add('spotify', 'error', '--- DAEMON START EXEC ERROR = '.$result.' ---');
      		return false;
    	  }

    	  $i = 0;
    	  while ($i < 30) {
      		
          	$deamon_info = self::deamon_info();
          	log::add('spotify', 'debug', '--- DAEMON info '.json_encode($deamon_info).'---');
          
      		if ($deamon_info['state'] == 'ok') {
        		break;
      		}
	        
          	log::add('spotify', 'debug', '--- DAEMON START WAIT LOOP = '.$i.'---');
      		sleep(1);
      		
          	$i++;
    	  }
      
    	  if ($i >= 30) {
	   		
          	log::add('spotify', 'debug', '--- DAEMON START FAILURE ---');
      		return false;
          
    	  }
    	
      	  message::removeAll('spotify', 'unableStartDeamon');
    	  log::add('spotify', 'debug', '--- DAEMON START SUCCEED ---');
      
      	  return true;
        
        } else {
          
          log::add('spotify', 'debug', '--- DAEMON ALREADY STARTED ---');
      	  return false;
          
        }
  
	}

  	public static function deamon_stop() {
      
      	log::add('spotify', 'debug', '--- DAEMON STOP ---');
   
      	$deamon_info = self::deamon_info();
        log::add('spotify', 'debug', '--- DAEMON INFO '.json_encode($deamon_info).'---');
      
      	if ($deamon_info['state'] == 'ok') {
      
      		log::add('spotify', 'debug', '--- DAEMON FIRST STOP ---');
      		
          	$cmd = 'sudo kill $(ps aux | grep "spotify.js" | awk \'{print $2}\')';
          	log::add('spotify', 'debug', '--- CMD = '.$cmd.' ---');
          
          	$result = exec($cmd);
      		log::add('spotify', 'debug', '--- RESULT = '.$result.' ---');
      
    		$deamon_info = self::deamon_info();
        	log::add('spotify', 'debug', '--- DAEMON INFO '.json_encode($deamon_info).'---');
      
    		if ($deamon_info['state'] == 'ok') {
              
      			log::add('spotify', 'debug', '--- DAEMON SECOND STOP ---');
      		
              	$cmd = 'sudo kill -9 $(ps aux | grep "spotify.js" | awk \'{print $2}\')';
              	log::add('spotify', 'debug', '--- CMD = '.$cmd.' ---');
              
          		$result = exec($cmd);
          		log::add('spotify', 'debug', '--- RESULT = '.$result.' ---');
      
          		sleep(1);
          
    			$deamon_info = self::deamon_info();
      			log::add('spotify', 'debug', '--- DAEMON INFO '.json_encode($deamon_info).'---');
      
    			if ($deamon_info['state'] == 'ok') {
                  
      				log::add('spotify', 'debug', '--- DAEMON THIRD STOP ---');
      		
                  	$cmd = 'sudo kill -9 $(ps aux | grep "spotify.js" | awk \'{print $2}\')';
                  	log::add('spotify', 'debug', '--- CMD = '.$cmd.' ---');
                  
          			$result = exec($cmd);
          			log::add('spotify', 'debug', '--- RESULT = '.$result.' ---');
          
          			sleep(1);
              
              		$deamon_info = self::deamon_info();
      				log::add('spotify', 'debug', '--- DAEMON INFO '.json_encode($deamon_info).'---');
      
      				if ($deamon_info['state'] == 'ok') {
                      
      					log::add('spotify', 'error', '--- DAEMON STOP FAILURE---');
          				return false;
                      
	        		}
                  
                } 
              
    		}
        
          	log::add('spotify', 'debug', '--- DAEMON STOP SUCCEED ---');
      		return true;
        
        } else  {
      	
      		log::add('spotify', 'debug', '--- DAEMON ALREADY STOPPED ---');
      		return false;
        
    	}
	}

  	public static function dependancy_info() {
      
      	log::add('spotify', 'debug', '--- DEPENDANCY INFO ---');
      
      	$return = array(); 
      
		$return['log'] = 'getAccessToken'; 
		$return['progress_file'] = '/tmp/spotify_dependancy'; 
      
      	system( "sudo /bin/bash ".dirname(__FILE__) . "/../../ressources/info.sh 2>/dev/null 1>&2",$code);
        
      	$return['state'] = ($code==0) ? 'ok' : 'nok'; 
        
		return $return; 
  	
    }

  	public static function dependancy_install() {
    
      	log::add('spotify', 'debug', '--- DEPENDANCY INSTALL ---');
      
      	if (file_exists('/tmp/spotify_dependancy')) { 
			return; 
		} 
      
		log::remove('getAccessToken'); 
      
		$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../ressources/install.sh'; 
		$cmd .= ' >> ' . log::getPathToLog('getAccessToken') . ' 2>&1 &'; 
      
		exec($cmd); 
      
  	}
  
  	public function previous( $_options = array()) {
    	
		$token = $this->getAccessToken();
		
      	if( $token != null ) {
      
          	log::add('spotify', 'debug', '--- PREVIOUS REQUESTED ---');
          
        	$api = new SpotifyWebAPI\SpotifyWebAPI();
          
        	$api->setAccessToken($token);    
          
          	$api->previous();
         
          	return true;
          
        } else {
          
          	log::add('spotify', 'debug', '--- PREVIOUS NOT AUTHORIZED ---');  
          	
          	return false;
        }
        
    }
  
	public function shuffle( $_options = array()) {
      
    	$token = $this->getAccessToken();
		
      	if( $token != null ) {
      
			log::add('spotify', 'debug', '--- SHUFFLE REQUESTED ---'); 
          
          	$api = new SpotifyWebAPI\SpotifyWebAPI();
          
        	$api->setAccessToken($token);     
          
            $option = Array();
          	$option['state'] = true;
          
        	$api->shuffle( $option);

          	return true;
          
        } else {
          
          	log::add('spotify', 'debug', '--- SHUFFLE NOT AUTHORIZED ---');  
          	
          	return false;
        }
      
    }
  
    public function unshuffle( $_options = array()) {
      
    	$token = $this->getAccessToken();
		
      	if( $token != null ) {
      
			log::add('spotify', 'debug', '--- UNSHUFFLE REQUESTED ---'); 
          
          	$api = new SpotifyWebAPI\SpotifyWebAPI();
          
        	$api->setAccessToken($token);     
          
            $option = Array();
          	$option['state'] = false;
          
        	$api->shuffle( $option);

          	return true;
          
        } else {
          
          	log::add('spotify', 'debug', '--- UNSHUFFLE NOT AUTHORIZED ---');  
          	
          	return false;
        }
      
    }
  
    public function play( $_options = array()) {
      
    	$token = $this->getAccessToken();
		
      	if( $token != null ) {
      
			log::add('spotify', 'debug', '--- PLAY REQUESTED ---'); 
          
          	$api = new SpotifyWebAPI\SpotifyWebAPI();
          
        	$api->setAccessToken($token);     
          
        	$api->play();

          	return true;
          
        } else {
          
          	log::add('spotify', 'debug', '--- PLAY NOT AUTHORIZED ---');  
          	
          	return false;
        }
      
    }
  
  	public function pause( $_options = array()) {
      
    	$token = $this->getAccessToken();
		
      	if( $token != null ) {
      
			log::add('spotify', 'debug', '--- PAUSE REQUESTED ---'); 
          
          	$api = new SpotifyWebAPI\SpotifyWebAPI();
          
        	$api->setAccessToken($token);    
          
        	$api->pause();

          	return true;
          
        } else {
          
          	log::add('spotify', 'debug', '--- PAUSE NOT AUTHORIZED ---');  
          	
          	return false;
        }
      
    }
  
    public function device( $_options = array()) {
      
    	$token = $this->getAccessToken();
      			
      	if( $token != null ) {
      
			log::add('spotify', 'debug', '--- DEVICE REQUESTED ---'); 
          
          	$device = '';
          
          	if( isset($_options['select']) ) {
              
          		$device = $_options['select'];
          
            } else {
              
              	$title = $_options['title'];   
              
              	if( $title == '') $title = $_options['message']; 
              
              	log::add('spotify', 'debug', '--- DEVICE NAME = ' . $title . ' ---'); 
              
              	$_device_id_set = $this->getCmd(null, 'device_id_set');
              	$_list = $_device_id_set->getConfiguration('listValue');
              	log::add('spotify', 'debug', '--- DEVICE LIST= ' . $_list . ' ---'); 
              
              	$_device = explode(";", $_list,50);
	  			$length = count($_device);
              
              	for ( $i = 0; $i < $length; $i++) {        
  	  				$_content = explode("|", $_device[$i], 2);
                  	log::add('spotify', 'debug', '--- DEVICE PARSE = ' .$_content[1] . ' ---'); 
                  	if(strtoupper($title)===strtoupper($_content[1])) {
                      	$device = $_content[0];
                      	log::add('spotify', 'debug', '--- DEVICE FOUND = ' . $device . ' ---'); 
                      	break;
                    }
	  			}
              
            }
          
          	log::add('spotify', 'debug', '--- DEVICE ID = ' . $device . ' ---'); 
          
          	if( $device != '' && strpos($device, "local.") === 0 ) {
            
              	$ip  = str_replace("local.", "", $device );
              	log::add('spotify', 'debug', '--- DEVICE --- ADDRESS = ' . $ip . ' ---'); 
              
              	$_device_id_set = $this->getCmd(null, 'device_id_set');
              	$_list = $_device_id_set->getConfiguration('listValue');
              	log::add('spotify', 'debug', '--- DEVICE LIST= ' . $_list . ' ---'); 
              
              	$_device = explode(";", $_list,50);
	  			$length = count($_device);
              	$name = "";
              
              	for ( $i = 0; $i < $length; $i++) {        
  	  				$_content = explode("|", $_device[$i], 2);
                  	log::add('spotify', 'debug', '--- DEVICE PARSE ' . $device . ' = ' .$_content[0] . ' / ' . $_content[1] . ' ---'); 
                  	if(strtoupper($device)===strtoupper($_content[0])) {
                      	$name = $_content[1];
                      	log::add('spotify', 'infog', '--- NAME FOUND = ' . $name . ' ---'); 
                      	break;
                    }
	  			}
              
              	$res = json_decode( $this->getCookieAccessToken() );
              	//$res = json_decode( $this->getLightAccessToken() );
              	
              	$token = $res->{accessToken};
              	//$token = $res->{access_token};
              	log::add('spotify', 'debug', '--- ACCESS TOKEN '.$token.' ---');
      			$this->setConfiguration('accesscookie', $token);
              
              	$expire = $res->{accessTokenExpirationTimestampMs};
             	//$expire = ( time() + $res->{expires_in} ) *1000;
             	log::add('spotify', 'debug', '--- EXPIRE TOKEN '.$expire.' ---');
      			$this->setConfiguration('expirecookie', $expire);
          		$this->setConfiguration('_expirecookie', date("Y-m-d H:i:s",$expire/1000));
              
        		$this->save();
              
              	$this->castv2( $name, $ip, $token, $expire);
              
              	return true;
                            
            } else if( $device != '' ) {
              
          		$api = new SpotifyWebAPI\SpotifyWebAPI();
          
        		$api->setAccessToken($token);    
          
          		$option = Array();
          		$option['device_ids'] = $device;
                $option['play'] = true;
                
        		$api->changeMyDevice($option);

          		return true;
              
       		} else {
              
              	return false;
              
            }
          
        } else {
          
          	log::add('spotify', 'debug', '--- DEVICE NOT AUTHORIZED ---');  
          	
          	return false;
        }
      
    }
  
    public function castv2( $name, $ip, $token, $expire) {
      
      	$device = "sender-0";
      	$requestId = 1;
      	
      	// ====
      	// OPEN
      	// ====
      
      	log::add('spotify', 'debug', '--- OPEN CHROMECAST --- BEGIN ---');
      
		$contextOptions = ['ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false ] ];
      
      	$context = stream_context_create($contextOptions);
      
		if ($socket = stream_socket_client('ssl://' . $ip . ":8009", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context)) {
          	stream_set_timeout($socket, 1);
			log::add('spotify', 'debug', '--- OPEN CHROMECAST --- END ---'); 	
		}
		else {
          	log::add('spotify', 'debug', 'ERROR ' . $errno . ' : ' . $errstr ); 
          	log::add('spotify', 'debug', '--- OPEN CHROMECAST --- FAILED ---'); 
          	throw new Exception("Failed to open chromecast");
		}
	
      	// ==============
      	// SEND : CONNECT
      	// ==============
      
    	log::add('spotify', 'debug', '--- CONNECT CHROMECAST --- BEGIN ---');
      
		$c0 = new CastMessage();
    	$c0->protocolversion = 0;
		$c0->source_id = $device;
		$c0->receiver_id = 'receiver-0';
		$c0->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
		$c0->payloadtype = 0;
      	$c0->payloadutf8 = '{ "type" : "CONNECT" }';
      
      	fwrite($socket, $c0->encode());
		fflush($socket);
      
    	log::add('spotify', 'debug', '--- CONNECT CHROMECAST --- END ---');
    
      	// =====================
        // SEND : LAUNCH SPOTIFY 
        // =====================
      
      	log::add('spotify', 'debug', '--- LAUNCH SPOTIFY --- BEGIN ---');

      	$c3 = new CastMessage();
      	$c3->source_id = $device;
      	$c3->receiver_id = 'receiver-0';
      	$c3->urnnamespace = "urn:x-cast:com.google.cast.receiver";
      	$c3->payloadtype = 0;
      	$c3->payloadutf8 = '{ "type" : "LAUNCH" , "appId" : "CC32E753", "requestId" : ' . $requestId++ . ' }';

      	fwrite($socket, $c3->encode());
      	fflush($socket);

      	log::add('spotify', 'debug', '--- LAUNCH SPOTIFY --- END ---');
      
    	// ==================
      	// %% MESSAGE LOOP %%
      	// ==================
      
      	$loop = 60;
      	
    	while ( $loop-- >= 1 ) {
        
          	$response = fread($socket, 2000);
          	log::add('spotify', 'debug', '--- LOOP CHROMECAST ( ' . $loop . ' ) <<< ' . $response . ' <<<');
          
          	$c1 = new CastMessage();
          	$c1->decode( $response, 1);
          	          
          	if( preg_match("/\"type\"\:\"([^\"]*)/", $c1->payloadutf8, $type) ) {
          	
          	  	log::add('spotify', 'debug', '--- LOOP CHROMECAST ( ' . $loop . ' ) %%% ' . $type[1] . ' %%%');
              
              	// ==============
              	// RECEIVE : PING
              	// ==============
              
                if ( $type[1] == "PING" ) {

                    log::add('spotify', 'debug', '--- PING --- BEGIN ---');
                  
                  	$c2 = new CastMessage();
                    $c2->protocolversion = 0;
					$c2->source_id = $c1->receiver_id;
                    $c2->receiver_id = $c1->source_id;
                    $c2->urnnamespace = "urn:x-cast:com.google.cast.tp.heartbeat";
                    $c2->payloadtype = 0;
                    $c2->payloadutf8 = '{ "type":"PONG" }';

                    fwrite($socket, $c2->encode());
                    fflush($socket);
                  
                  	log::add('spotify', 'debug', '--- PING --- END ---');
                
                // =========================
                // RECEIVE : RECEIVER STATUS
                // =========================
                  
                }  else if( $type[1] == "RECEIVER_STATUS" && preg_match("/\"transportId\"/", $c1->payloadutf8) && preg_match("/\"sessionId\"/", $c1->payloadutf8) ) {
                 
                	log::add('spotify', 'debug', '--- RECEIVER STATUS --- BEGIN ---');
                  
                  	preg_match("/\"transportId\"\:\"([^\"]*)/", $c1->payloadutf8, $matches);
					$transportid = $matches[1];
					log::add('spotify', 'debug', '--- WAIT SPOTIFY %%% TRANSPORT ID = ' . $transportid . ' ---');
      
					preg_match("/\"sessionId\"\:\"([^\"]*)/", $c1->payloadutf8, $matches);
					$sessionid = $matches[1];
					log::add('spotify', 'debug', '--- WAIT SPOTIFY %%% SESSION ID = ' . $sessionid . ' ---');  
                  
                  	log::add('spotify', 'debug', '--- RECEIVER STATUS --- END ---');
                  
                  	// ==============
                  	// SEND : CONNECT 
                    // ==============
                  
                  	log::add('spotify', 'debug', '--- SPOTIFY CONNECT --- BEGIN ---');
                  
                  	$c5 = new CastMessage();
                  	$c5->source_id = $device;
                  	$c5->receiver_id = $transportid;
                  	$c5->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
					$c5->payloadtype = 0;
      				$c5->payloadutf8 = '{ "type" : "CONNECT" }';
				
                  	fwrite($socket, $c5->encode());
                  	fflush($socket);      
                  	
                  	log::add('spotify', 'debug', '--- SPOTIFY CONNECT --- END ---');
                  
                  	log::add('spotify', 'debug', '--- SPOTIFY AUTH --- BEGIN ---');
                  
                    // sp_dc=AQC8johYj4nhaBHnHSgsZvJPxhsI8NCVBAW2lLpjoPQQa2PdM7aDC67MbriH4hkemSsdlmn4aZMkaEPoEmX6PwPQQjXd4qz8hJdr0RxzBARt7A
					// sp_key=74fecec5-1355-46ab-8650-8c7f4c18961e

                  	$c4 = new CastMessage();
                  	$c4->source_id = $device;
                  	$c4->receiver_id = $transportid;
                  	$c4->urnnamespace = "urn:x-cast:com.spotify.chromecast.secure.v1";
                  	$c4->payloadtype = 0;
                  	$c4->payloadutf8 = '{ "type" : "setCredentials", "credentials" : "' . $token . '", "expiresIn" : ' . $expire . ' }';
				
                  	log::add('spotify', 'debug', $c4->payloadutf8);
                  
                  	fwrite($socket, $c4->encode());
                  	fflush($socket);      
                  	
                  	log::add('spotify', 'debug', '--- SPOTIFY AUTH --- END ---');
                  	
                // ===============================
                // RECEIVE : SET CREDENTIALS ERROR
                // ===============================
                  
                } else if ( $type[1] == "setCredentialsError" ) {
                
                  	log::add('spotify', 'debug', '--- SPOTIFY ERROR --- BEGIN ---');
                  
                  	log::add('spotify', 'debug', '--- SPOTIFY ERROR --- END ---');
                  
                  	$loop = -1;
                                    
                  	throw new Exception($type[1].": erreur de connexion");
                  
                // ==================================
                // RECEIVE : SET CREDENTIALS RESPONSE
                // ==================================
                
                } else if ( $type[1] == "setCredentialsResponse" ) {
                  
                  	log::add('spotify', 'debug', '--- SPOTIFY OK --- BEGIN ---');
                  
                  	$api = new SpotifyWebAPI\SpotifyWebAPI();
          
                  $_token = $this->getAccessToken();
        			$api->setAccessToken($_token);    
          
                  	$api->setReturnType(SpotifyWebAPI\SpotifyWebAPI::RETURN_ASSOC);
                  	$devices = $api->getMyDevices();
                  
                  	$id="";
                  
                  	foreach ($devices['devices'] as $device) {
                  		log::add('spotify', 'debug', '--- ' . $name . ' ? ' . $device['id'] . ' / ' . $device['name'] . ' ---');
                      	if( $device['name'] == $name ) {
                          	log::add('spotify', 'debug', '%%% TRUE  %%% ' . $device['id'] . ' / ' . $device['name'] . ' %%%');
                          	$option = Array();
          					$option['device_ids'] = $device['id'];
                            $option['play'] = true;
                  			$api->changeMyDevice($option);
                          	break;
                        } else {
                          	log::add('spotify', 'debug', '%%% FALSE %%% ' . $device['id'] . ' / ' . $device['name'] . ' %%%');
                        }
                    }
                  
                  	$loop = -1;
                    
                    log::add('spotify', 'debug', '--- SPOTIFY OK --- END ---');
                  
                }
              
            } 
               
        }
      
	} 
  
    public function playlist( $_options = array()) {
      
    	$token = $this->getAccessToken();
		
      	if( $token != null ) {
      
          	log::add('spotify', 'debug', '--- PLAYLIST REQUESTED ---'); 
          
          	$playlist = '';
          
          	if( isset($_options['select']) ) {
			
              	$playlist = $_options['select'];
          		
            } else {
              
              	$title = $_options['title']; 
              
              	if( $title == '') $title = $_options['message']; 
              
              	$title = str_replace("\n", " ", $title);
                log::add('spotify', 'debug', '--- PLAYLIST NAME = ' . $title . ' ---'); 
              
              	$_playlist_id_set = $this->getCmd(null, 'playlist_id_set');
              	$_list = $_playlist_id_set->getConfiguration('listValue');
              
              	$_playlist = explode(";", $_list,50);
	  			$length = count($_playlist);
              
              	for ( $i = 0; $i < $length; $i++) {        
  	  				$_content = explode("|", $_playlist[$i], 2);
                  	log::add('spotify', 'debug', '--- PLAYLIST PARSE = ' .$_content[1] . ' ---'); 
                  	if(strtoupper($title)===strtoupper($_content[1])) {
                      	$playlist = $_content[0];
          				log::add('spotify', 'debug', '--- DEVICE FOUND = ' . $playlist . ' ---'); 
                      	break;
                    }
	  			}

            }
          
            log::add('spotify', 'debug', '--- PLAYLIST ID = ' . $playlist . ' ---'); 
          
          	if( $playlist != '' ) {
          
           		$api = new SpotifyWebAPI\SpotifyWebAPI();
          
        		$api->setAccessToken($token);    
          
          		$_user_id = $api->me()->id; 
          		log::add('spotify', 'debug', '--- USER ID ' . $_user_id . ' ---');  
          
          		// $_uri = 'spotify:user:' . $_user_id . ':playlist:' . $playlist;
              	$_uri = $playlist;
          		log::add('spotify', 'debug', '--- URI ' . $_uri . ' ---');  
          
          		$option = Array();
          		$option['context_uri'] =  $_uri;
          
          		$api->play( '', $option);

          		return true;
            
            } else {
              
            	return false;
            
            }
            
        } else {
          
          	log::add('spotify', 'debug', '--- PLAYLIST NOT AUTHORIZED ---');  
          	
          	return false;
        }
      
    }
  
    public function volume( $_options = array()) {
      
    	$token = $this->getAccessToken();
		
      	if( $token != null ) {
      
			log::add('spotify', 'debug', '--- VOLUME REQUESTED ---'); 
          
          	$api = new SpotifyWebAPI\SpotifyWebAPI();
          
        	$api->setAccessToken($token);    
          
          	$option = Array();
          	$option['volume_percent'] = $_options['slider'];
          
        	$api->changeVolume($option);

          	return true;
          
        } else {
          
          	log::add('spotify', 'debug', '--- VOLUME NOT AUTHORIZED ---');  
          	
          	return false;
        }
      
    }
  
 	public function next( $_options = array()) {
    	
		$token = $this->getAccessToken();
		
      	if( $token != null ) {
      
        	log::add('spotify', 'debug', '--- NEXT REQUESTED ---');
          
          	$api = new SpotifyWebAPI\SpotifyWebAPI();
          
        	$api->setAccessToken($token);    
          
          	$api->next();
          
          	return true;
          
        } else {
          
          	log::add('spotify', 'debug', '--- NEXT NOT AUTHORIZED ---');  
          	
          	return false;
        }
        
    }
  
  	protected function getCookieAccessToken() {
      
    	$cookie = $this->getConfiguration('cookie');   
      	log::add('spotify', 'debug', '--- COOKIE '.$cookie.' ---');   

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            'https://open.spotify.com/get_access_token?reason=transport&productType=web_player' );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
      	curl_setopt($ch, CURLOPT_HTTPHEADER,     array(
          //'app-platform: WebPlayer',
          'cookie: '.$cookie, 
       // 'referer: https://open.spotify.com/',
          //'refer: https://open.spotify.com/?_gl=1*prrsxf*_gcl_aw*R0NMLjE2MTgzMjQ3ODEuQ2owS0NRandndFdEQmhEWkFSSXNBREVLd2dPMjJ5Nm05STRwUzFMQkVxaENuNFhJSUJtLVk1MXJwYllrbGlQOVdLdzIyTkxMUlY2YWtGa2FBdVR3RUFMd193Y0I.*_gcl_dc*R0NMLjE2MTgzMjQ3ODEuQ2owS0NRandndFdEQmhEWkFSSXNBREVLd2dPMjJ5Nm05STRwUzFMQkVxaENuNFhJSUJtLVk1MXJwYllrbGlQOVdLdzIyTkxMUlY2YWtGa2FBdVR3RUFMd193Y0I.',          
       // 'sec-ch-ua: "Google Chrome";v="89", "Chromium";v="89", ";Not\"A\\Brand";v="99"',
          //'sec-ch-ua-mobile: ?1',
          //'sec-fetch-user: ?1', 
       // 'sec-fetch-mode: navigate',
          //'sec-fetch-mode: cors',
	   // 'sec-fetch-dest: document',
          //'sec-fetch-dest: empty',
          //'sec-fetch-site: same-origin',
          //'spotify-app-version: 1584347062',
       // 'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36') 
          'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36')
        ); 
      
      

		$result=curl_exec($ch);
  
      	log::add('spotify', 'debug', '--- COOKIE ACCESS TOKEN '.$result.' ---');   
      		
      	return $result;
      
    }
  
    protected function getLightAccessToken() {
      
    	$clientid = config::byKey('clientid', 'spotify');      
      	log::add('spotify', 'debug', '--- CLIENT ID '.$clientid.' ---');   
      
      	$clientsecret = config::byKey('clientsecret', 'spotify');
      	log::add('spotify', 'debug', '--- CLIENT SECRET '.$clientsecret.' ---');   

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            'https://accounts.spotify.com/api/token' );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($ch, CURLOPT_POST,           1 );
        curl_setopt($ch, CURLOPT_POSTFIELDS,     'grant_type=client_credentials' ); 
        curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Authorization: Basic '.base64_encode($clientid.':'.$clientsecret))); 
		$result=curl_exec($ch);
      
		log::add('spotify', 'debug', '--- LIGHT ACCESS TOKEN '.$result.' ---');   
      		
      	return $result;
      
    }
  
  	public function getAccessToken() {
  
    	$clientid = config::byKey('clientid', 'spotify');      
      	log::add('spotify', 'debug', '--- CLIENT ID '.$clientid.' ---');   
      
      	$clientsecret = config::byKey('clientsecret', 'spotify');
      	log::add('spotify', 'debug', '--- CLIENT SECRET '.$clientsecret.' ---');   
      
      	$expire = $this->getConfiguration('expire');
        log::add('spotify', 'debug', '--- EXPIRATION TIME '.$expire.' ---'); 
      
      	$access = $this->getConfiguration('access');
        log::add('spotify', 'debug', '--- ACCESS TOKEN '.$access.' ---'); 
      
      	$refresh = $this->getConfiguration('refresh');
        log::add('spotify', 'debug', '--- REFRESH TOKEN '.$refresh.' ---');    
      
      	if( $expire - time() <= 0 ) {
          
        	$session = new SpotifyWebAPI\Session( $clientid, $clientsecret);
        	$session->refreshAccessToken($refresh);
          
          	$access = $session->getAccessToken();
      		log::add('spotify', 'debug', '--- ACCESS TOKEN '.$access.' ---');   
      		$this->setConfiguration('access', $access);
          
			$refresh = $session->getRefreshToken();
      		log::add('spotify', 'debug', '--- REFRESH TOKEN '.$refresh.' ---');  
         	$this->setConfiguration('refresh', $refresh);
          	
     		$expire = $session->getTokenExpiration();
      		log::add('spotify', 'debug', '--- EXPIRE '.$expire.' ---');  
         	$this->setConfiguration('expire', $expire);          
          	$this->setConfiguration('_expire', date("Y-m-d H:i:s",$expire));
              
          	$this->save();
        }
      
      	return $access;
        
    }  
  
  	/**************** Methods ****************/

  	// preInsert ? Méthode appelée avant la création de votre objet
	// postInsert ? Méthode appelée après la création de votre objet
	// preUpdate ? Méthode appelée avant la mise à jour de votre objet
	// postUpdate ? Méthode appelée après la mise à jour de votre objet
	// preSave ? Méthode appelée avant la sauvegarde (création et mise à jour donc) de votre objet
	// postSave ? Méthode appelée après la sauvegarde de votre objet
	// preRemove ? Méthode appelée avant la suppression de votre objet
	// postRemove ? Méthode appelée après la suppression de votre objet 
    
  	public function postSave() {
      
      	$order = 1;
      
      	// ==============
      	// === DEVICE ===
      	// ==============
      
      	// DEVICE IS ACTIVE
      
      	$device_is_active = $this->getCmd(null, 'device_is_active');
		
      	if (!is_object($device_is_active)) {
			$device_is_active = new spotifyCmd();
			$device_is_active->setLogicalId('device_is_active');
			$device_is_active->setIsVisible(0);
			$device_is_active->setName(__('Device Is Active', __FILE__));
		}
      
		$device_is_active->setType('info');
		$device_is_active->setSubType('string');
		$device_is_active->setEqLogic_id($this->getId());
      	$device_is_active->setDisplay('forceReturnLineBefore',1);
      	$device_is_active->setOrder($order++);
      	$device_is_active->save();
      
        // DEVICE ID 
      
      	$device_id = $this->getCmd(null, 'device_id');
		
      	if (!is_object($device_id)) {
			$device_id = new spotifyCmd();
			$device_id->setLogicalId('device_id');
			$device_id->setIsVisible(0);
			$device_id->setName(__('Device Id', __FILE__));
		}
      
		$device_id->setType('info');
		$device_id->setSubType('string');
		$device_id->setEqLogic_id($this->getId());
      	$device_id->setDisplay('forceReturnLineBefore',1);
      	$device_id->setOrder($order++);
      	$device_id->save();
      
      	// DEVICE ID SET
      
      	$device_id_set = $this->getCmd(null, 'device_id_set');
		
      	if (!is_object($device_id_set)) {
			$device_id_set = new spotifyCmd();
			$device_id_set->setLogicalId('device_id_set');
			$device_id_set->setIsVisible(1);
			$device_id_set->setName(__('Device Id Set', __FILE__));
		}
      
		$device_id_set->setType('action');
		$device_id_set->setSubType('select');
		$device_id_set->setEqLogic_id($this->getId());
      	$device_id_set->setValue($device_id->getId());
      	$device_id_set->setDisplay('forceReturnLineBefore',1);
      	$device_id_set->setOrder($order++);
      	$device_id_set->save();
      
        // DEVICE NAME 
      
      	$device_name = $this->getCmd(null, 'device_name');
		
      	if (!is_object($device_name)) {
			$device_name = new spotifyCmd();
			$device_name->setLogicalId('device_name');
			$device_name->setIsVisible(0);
			$device_name->setName(__('Device Name', __FILE__));
		}
      
		$device_name->setType('info');
		$device_name->setSubType('string');
		$device_name->setEqLogic_id($this->getId());
      	$device_name->setDisplay('forceReturnLineBefore',1);
      	$device_name->setOrder($order++);
      	$device_name->save();             
      
        // DEVICE NAME SET
      
        $device_name_set = $this->getCmd(null, 'device_name_set');
		
      	if (!is_object($device_name_set)) {
			$device_name_set = new spotifyCmd();
			$device_name_set->setLogicalId('device_name_set');
			$device_name_set->setIsVisible(0);
			$device_name_set->setName(__('Device Name Set', __FILE__));
		}
      
		$device_name_set->setType('action');
		$device_name_set->setSubType('message');
		$device_name_set->setEqLogic_id($this->getId());
      	$device_name_set->setDisplay('forceReturnLineBefore',1);
      	$device_name_set->setOrder($order++);
      	$device_name_set->save();
      
        // DEVICE VOLUME
      
      	$device_volume = $this->getCmd(null, 'device_volume');
		
      	if (!is_object($device_volume)) {
			$device_volume = new spotifyCmd();
			$device_volume->setLogicalId('device_volume');
			$device_volume->setIsVisible(0);
			$device_volume->setName(__('Device Volume', __FILE__));
		}
      
		$device_volume->setUnite('%');
		$device_volume->setType('info');
		$device_volume->setSubType('numeric');
		$device_volume->setEqLogic_id($this->getId());
      	$device_volume->setDisplay('forceReturnLineBefore',1);
      	$device_volume->setOrder($order++);
		$device_volume->save();
      	
        // DEVICE VOLUME SET
      
      	$device_volume_set = $this->getCmd(null, 'device_volume_set');
		
      	if (!is_object($device_volume_set)) {
			$device_volume_set = new spotifyCmd();
			$device_volume_set->setLogicalId('device_volume_set');
			$device_volume_set->setIsVisible(1);
			$device_volume_set->setName(__('Device Volume Set', __FILE__));
		}
      
		$device_volume_set->setType('action');
		$device_volume_set->setSubType('slider');
		$device_volume_set->setEqLogic_id($this->getId());
      	$device_volume_set->setValue($device_volume->getId());
      	$device_volume_set->setOrder($order++);
      	$device_volume_set->setDisplay('forceReturnLineBefore',1);
		$device_volume_set->save();
      
      	// ============
      	// === ITEM ===
      	// ============
      
      	// ITEM ID 
      
      	$item_id = $this->getCmd(null, 'item_id');
		
      	if (!is_object($item_id)) {
			$item_id = new spotifyCmd();
			$item_id->setLogicalId('item_id');
			$item_id->setIsVisible(0);
			$item_id->setName(__('Item Id', __FILE__));
		}
      
		$item_id->setType('info');
      	$item_id->setSubType('string');
		$item_id->setEqLogic_id($this->getId());
      	$item_id->setOrder($order++);
		$item_id->save();
      
      	// ITEM ALBUM 
      
      	$item_album = $this->getCmd(null, 'item_album');
		
      	if (!is_object($item_album)) {
			$item_album = new spotifyCmd();
			$item_album->setLogicalId('item_album');
			$item_album->setIsVisible(1);
			$item_album->setName(__('Item Album', __FILE__));
		}
      
		$item_album->setType('info');
      	$item_album->setSubType('string');
		$item_album->setEqLogic_id($this->getId());
      	$item_album->setOrder($order++);
		$item_album->save();
      
      	// ITEM TITLE 
      
      	$item_title = $this->getCmd(null, 'item_title');
		
      	if (!is_object($item_title)) {
			$item_title = new spotifyCmd();
			$item_title->setLogicalId('item_title');
			$item_title->setIsVisible(1);
			$item_title->setName(__('Item Title', __FILE__));
		}
      
		$item_title->setType('info');
		$item_title->setSubType('string');
		$item_title->setEqLogic_id($this->getId());
      	$item_title->setOrder($order++);
		$item_title->save();
      
      	// ITEM ARTIST 
      
      	$item_artist = $this->getCmd(null, 'item_artist');
		
      	if (!is_object($item_artist)) {
			$item_artist = new spotifyCmd();
			$item_artist->setLogicalId('item_artist');
			$item_artist->setIsVisible(1);
			$item_artist->setName(__('Item Artist', __FILE__));
		}
      
		$item_artist->setType('info');
		$item_artist->setSubType('string');
		$item_artist->setEqLogic_id($this->getId());
      	$item_artist->setOrder($order++);
		$item_artist->save();
      	
      	// ITEM IMAGE 
      
      	$item_image = $this->getCmd(null, 'item_image');
		
      	if (!is_object($item_image)) {
			$item_image = new spotifyCmd();
			$item_image->setLogicalId('item_image');
			$item_image->setIsVisible(1);
			$item_image->setName(__('Item Image', __FILE__));
		}
      
		$item_image->setType('info');
		$item_image->setSubType('string');
		$item_image->setEqLogic_id($this->getId());
      	$item_image->setOrder($order++);
      	$item_image->setTemplate('dashboard','spotify::Image');
      	$item_image->setDisplay('forceReturnLineBefore',1);
		$item_image->save();
      
      	// PLAYLIST ID 
      
      	$playlist_id = $this->getCmd(null, 'playlist_id');
		
      	if (!is_object($playlist_id)) {
			$playlist_id = new spotifyCmd();
			$playlist_id->setLogicalId('playlist_id');
			$playlist_id->setIsVisible(0);
			$playlist_id->setName(__('Playlist Id', __FILE__));
		}
      
		$playlist_id->setType('info');
		$playlist_id->setSubType('string');
		$playlist_id->setEqLogic_id($this->getId());
      	$playlist_id->setOrder($order++);
      	$playlist_id->save();
      
        // PLAYLIST ID SET
      
      	$playlist_id_set = $this->getCmd(null, 'playlist_id_set');
		
      	if (!is_object($playlist_id_set)) {
			$playlist_id_set = new spotifyCmd();
			$playlist_id_set->setLogicalId('playlist_id_set');
			$playlist_id_set->setIsVisible(1);
			$playlist_id_set->setName(__('Playlist Id Set', __FILE__));
		}
      
		$playlist_id_set->setType('action');
		$playlist_id_set->setSubType('select');
		$playlist_id_set->setEqLogic_id($this->getId());
      	$playlist_id_set->setValue($playlist_id->getId());
        $playlist_id_set->setDisplay('forceReturnLineBefore',1);
      	$playlist_id_set->setOrder($order++);
      	$playlist_id_set->save();
      
      	// PLAYLIST NAME 
      
      	$playlist_name = $this->getCmd(null, 'playlist_name');
		
      	if (!is_object($playlist_name)) {
			$playlist_name = new spotifyCmd();
			$playlist_name->setLogicalId('playlist_name');
			$playlist_name->setIsVisible(0);
			$playlist_name->setName(__('Playlist Name', __FILE__));
		}
      
		$playlist_name->setType('info');
		$playlist_name->setSubType('string');
		$playlist_name->setEqLogic_id($this->getId());
      	$playlist_name->setOrder($order++);
      	$playlist_name->save();
      
        // PLAYLIST NAME SET
      
        $playlist_name_set = $this->getCmd(null, 'playlist_name_set');
		
      	if (!is_object($playlist_name_set)) {
			$playlist_name_set = new spotifyCmd();
			$playlist_name_set->setLogicalId('playlist_name_set');
			$playlist_name_set->setIsVisible(0);
			$playlist_name_set->setName(__('Playlist Name Set', __FILE__));
		}
      
		$playlist_name_set->setType('action');
		$playlist_name_set->setSubType('message');
		$playlist_name_set->setEqLogic_id($this->getId());
      	$playlist_name_set->setOrder($order++);
      	$playlist_name_set->save();
      
        // ==============
      	// === ACTION ===
      	// ==============
      	
      	// SHUFFLE
      
        $shuffling = $this->getCmd(null, 'shuffling');
		
      	if (!is_object($shuffling)) {
			$shuffling = new spotifyCmd();
			$shuffling->setLogicalId('shuffling');
			$shuffling->setIsVisible(1);
			$shuffling->setName(__('Shuffling', __FILE__));
		}
      
		$shuffling->setType('info');
		$shuffling->setSubType('string');
		$shuffling->setEqLogic_id($this->getId());
      	$shuffling->setOrder($order++);
		$shuffling->save();
      
        // PLAYING
      
        $playing = $this->getCmd(null, 'playing');
		
      	if (!is_object($playing)) {
			$playing = new spotifyCmd();
			$playing->setLogicalId('playing');
			$playing->setIsVisible(1);
			$playing->setName(__('Playing', __FILE__));
		}
      
		$playing->setType('info');
		$playing->setSubType('string');
		$playing->setEqLogic_id($this->getId());
      	$playing->setOrder($order++);
		$playing->save();
      
      	
      	// PREVIOUS
      
      	$previous = $this->getCmd(null, 'previous');
		
      	if (!is_object($previous)) {
			$previous = new spotifyCmd();
			$previous->setLogicalId('previous');
			$previous->setIsVisible(1);
			$previous->setName(__('Previous', __FILE__));
		}
      
		$previous->setType('action');
		$previous->setSubType('other');
		$previous->setEqLogic_id($this->getId());
      	$previous->setOrder($order++);
      	$previous->setDisplay('forceReturnLineBefore',1);
		$previous->save();
      
      	// PLAY
      
		$play = $this->getCmd(null, 'play');
		
      	if (!is_object($play)) {
			$play = new spotifyCmd();
			$play->setLogicalId('play');
			$play->setIsVisible(1);
			$play->setName(__('Play', __FILE__));
		}
      
		$play->setType('action');
		$play->setSubType('other');
		$play->setEqLogic_id($this->getId());
      	$play->setOrder($order++);
		$play->setDisplay('forceReturnLineBefore',0);
		$play->save();
      
      	// PAUSE
      
      	$pause = $this->getCmd(null, 'pause');
		
      	if (!is_object($pause)) {
			$pause = new spotifyCmd();
			$pause->setLogicalId('pause');
			$pause->setIsVisible(1);
			$pause->setName(__('Pause', __FILE__));
		}
      
		$pause->setType('action');
		$pause->setSubType('other');
		$pause->setEqLogic_id($this->getId());
      	$pause->setOrder($order++);
		$pause->setDisplay('forceReturnLineBefore',0);
		$pause->save();
      
      	// NEXT
      
      	$next = $this->getCmd(null, 'next');
		
      	if (!is_object($next)) {
			$next = new spotifyCmd();
			$next->setLogicalId('next');
			$next->setIsVisible(1);
			$next->setName(__('Next', __FILE__));
		}
      
		$next->setType('action');
		$next->setSubType('other');
		$next->setEqLogic_id($this->getId());
      	$next->setOrder($order++);
      	$next->setDisplay('forceReturnLineBefore',0);
		$next->save();
      
      	// SHUFFLE
      
      	$shuffle = $this->getCmd(null, 'shuffle');
		
      	if (!is_object($shuffle)) {
			$shuffle = new spotifyCmd();
			$shuffle->setLogicalId('shuffle');
			$shuffle->setIsVisible(1);
			$shuffle->setName(__('Shuffle', __FILE__));
		}
      
		$shuffle->setType('action');
		$shuffle->setSubType('other');
		$shuffle->setEqLogic_id($this->getId());
      	$shuffle->setOrder($order++);
      	$shuffle->setDisplay('forceReturnLineBefore',0);
		$shuffle->save();
      
      	// UNSHUFFLE
      
      	$unshuffle = $this->getCmd(null, 'unshuffle');
		
      	if (!is_object($unshuffle)) {
			$unshuffle = new spotifyCmd();
			$unshuffle->setLogicalId('unshuffle');
			$unshuffle->setIsVisible(1);
			$unshuffle->setName(__('Unshuffle', __FILE__));
		}
      
		$unshuffle->setType('action');
		$unshuffle->setSubType('other');
		$unshuffle->setEqLogic_id($this->getId());
      	$unshuffle->setOrder($order++);
      	$unshuffle->setDisplay('forceReturnLineBefore',0);
		$unshuffle->save();
      
      	spotify::deamon_start();
      
    }
  
  	/********** Getters and setters **********/
  
}

class spotifyCmd extends cmd {

	/*************** Attributs ***************/

	/************* Static methods ************/

  	/**************** Methods ****************/
  	
  	
  	public function formatValue( $_value, $_quote = false) {
      
      	$value = cmd::formatValue( $_value, $_quote);
      	
      	//log::add('spotify', 'debug', '--- FORMAT VALUE '.$this->getLogicalId().' / '.json_encode($_value).' / '.json_encode($_quote).' / '.json_encode($value).' ---');    
      	
      	return $value;
      
	}
  
  	public function getCmdValue() {
    
      	$value = cmd::getCmdValue();
      
      	//log::add('spotify', 'debug', '--- GET CMD VALUE '.$this->getLogicalId().' / '.json_encode($value).' ---');    
      	
      	return $value;
      
  	}
  
  	public function getValue() {
    
      	$value = cmd::getValue();
      
      	//log::add('spotify', 'debug', '--- GET VALUE '.$this->getLogicalId().' / '.json_encode($value).' ---');    
      	
      	return $value;
      
  	}
  
  	public function execute($_options = array()) {
    
      	if ($this->getLogicalId() == 'previous')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->previous( $_options);

      	}
      
      	if ($this->getLogicalId() == 'next')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->next( $_options);

      	}
      
      	if ($this->getLogicalId() == 'play')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->play( $_options);

      	}

      	if ($this->getLogicalId() == 'pause')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->pause( $_options);

      	}
      
        if ($this->getLogicalId() == 'shuffle')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->shuffle( $_options);

      	}
      
        if ($this->getLogicalId() == 'unshuffle')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->unshuffle( $_options);

      	}
      
        if ($this->getLogicalId() == 'device_volume_set')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->volume( $_options);

      	}
      
        if ($this->getLogicalId() == 'device_id_set')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->device( $_options);

      	}
      
        if ($this->getLogicalId() == 'device_name_set')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->device( $_options);

      	}
      
      	if ($this->getLogicalId() == 'playlist_id_set')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->playlist( $_options);

      	}
      
        if ($this->getLogicalId() == 'playlist_name_set')
      	{
        	
          	log::add('spotify', 'debug', '--- EXECUTE '.$this->getLogicalId().' / '.json_encode($_options).' ---');    
            $this->getEqLogic()->playlist( $_options);

      	}
      
  	}

  	/********** Getters and setters **********/

}