var SpotifyWebApi = require('spotify-web-api-node');
var http = require('http');
var https = require('https');
var dateFormat = require('dateformat');

// ========================
// === SPOTIFY ACCOUNTS ===
// ========================

var access = Array();
var refresh = Array();
var expire = Array();
var device_id = Array()
var device_is_active = Array();
var device_name = Array();
var device_type = Array();
var device_volume = Array();
var shuffle_state = Array();
var item_id = Array();
var item_artist = Array();
var item_title = Array();
var item_album = Array();
var item_image = Array();
var context_type = Array();
var context_uri = Array();
var playlist_id = Array();
var playlist_name = Array();
var counter = Array();

// ==================
// === PARAMETERS ===
// ==================

var configUrl = process.argv[2];
var	level = process.argv[3];
var protocol = process.argv[4];
var delay = 1000;
var count = 30;

// ====================
// == LOGGER LEVELS ===
// ====================

const DEBUG = 100;
const INFO = 200;
const WARNING = 300;
const ERROR = 400;

// =======================================
// === CONVERT UTC DATE TO LOCALE DATE ===
// =======================================

function convertUTCDateToLocalDate(date) {
  var newDate = new Date(date.getTime()+date.getTimezoneOffset()*60*1000);
  var offset = date.getTimezoneOffset() / 60;
  var hours = date.getHours();
  newDate.setHours(hours - offset);
  return newDate;   
}

// ===========
// === LOG ===
// ===========

function log( lvl, fct, str) {
  var now = convertUTCDateToLocalDate(new Date());
  var datestr = dateFormat(now, "yyyy:mm:dd hh:MM:ss");
  var fctstr = fct.substring(0, 20) +  ' '.repeat(Math.max(20-fct.length,0));
  console.log( "[" + datestr + "][" + lvl + "][" + fctstr + '] ' + str);
}

// =============
// === DEBUG ===
// =============

function debug( fct, str) {
  if( level <= DEBUG) log( "DEBUG", fct, str);
}

// =============
// === DEBUG ===
// =============

function info( fct, str) {
  if( level <= INFO) log( "INFO-", fct, str);
}

// =============
// === DEBUG ===
// =============

function warning( fct, str) {
  if( level <= WARNING) log( "WARN-", fct, str);
}

// =============
// === EROOR ===
// =============

function error( fct, str) {
  if( level <= ERROR) log( "ERROR", fct, str);
}

// ==============
// === CONFIG ===
// ==============

var _started = false;
var _apikey = null;
var _commands = null;        
var _itemcallback = null;
var _devicecallback = null;
var _playlistcallback = null;
var _shufflecallback = null;
var _refreshcallback = null;

// =======================
// === CONFIG RESPONSE ===
// =======================

function configResponse( json) {
	
	debug('configResponse','BEGIN');  
        
  	var data = JSON.parse(json);
	info('configRequest', '<< ' + JSON.stringify(data));
				
  	_apikey = data['result']['apikey'];
  	info('configResponse','API KEY = '+_apikey);

  	_commands = JSON.parse(data['result']['commands']);    
  	info('configResponse','COMMANDS = '+data['result']['commands']);

  	_itemcallback = data['result']['itemcallback'];
  	info('configResponse','ITEM CALLBACK = '+_itemcallback);

  	_devicecallback = data['result']['devicecallback'];
  	info('configResponse','DEVICE CALLBACK = '+_devicecallback);

  	_playlistcallback = data['result']['playlistcallback'];
  	info('configResponse','PLAYLIST CALLBACK = '+_playlistcallback);

  	_shufflecallback = data['result']['shufflecallback'];
  	info('configResponse','SHUFFLE CALLBACK = '+_shufflecallback);

  	_refreshcallback = data['result']['refreshcallback'];
  	info('configResponse','REFRESH CALLBACK = '+_refreshcallback);

  	debug('configResponse','END'); 
  
    _started = true;
  
}

// ======================
// === CONFIG REQUEST ===
// ======================

function configRequest() {
  
	debug('configRequest','BEGIN');  
 
  	debug('configRequest','PROTOCOL = '+protocol);
  	
  	info('configRequest','>> ' + configUrl);
      
  	if( protocol == 'HTTP' ) {
  	
    	http.get( configUrl, (resp) => {
      
        	let _data = '';
     
          	resp.on('data', (chunk) => {
          		_data += chunk;
      		});
      
          	resp.on('end', () => {
				configResponse( _data);
      		});
  
        }).on("error", (err) => {
      		error('configRequest','Failed to http get config ' + err);
  		});
      
	} else {
  
      	https.get( configUrl, (resp) => {
      
        	let _data = '';
      
          	resp.on('data', (chunk) => {
          		_data += chunk;
      		});
          
      		resp.on('end', () => {
              	configResponse( _data);
      		});
  
		}).on("error", (err) => {
			error('configRequest','Failed to https get config ' + err);
  		});  
	}
  
  	debug('configRequest','END');  
  
}

// =======================================
// === CURRENT PLAYBACK STATE RESPONSE ===
// =======================================

function playbackStateResponse( _index, data) {
	
	var _item_id = '';         
   	var _item_title = '';         
   	var _item_artist = '';       
   	var _item_album = '';    
   	var _item_image = '';   
	var _context_type = '';
    var _context_uri = '';
    
  	debug('playbackStateResponse','BEGIN');  
	
  	info('playbackStateResponse', '<< ' + JSON.stringify(data));
				
    if ( data.body['is_playing'] && data.body['item'] ) {

      	info('playbackStateResponse','ITEM ID = '+data.body['item']['id']);
		_item_id = data.body['item']['id']; 
      
        info('playbackStateResponse','ITEM TITLE = '+data.body['item']['name']);
      	_item_title = data.body['item']['name']; 
        
      	info('playbackStateResponse','ITEM ARTIST = '+data.body['item']['artists']['0']['name']);
      	_item_artist = data.body['item']['artists']['0']['name'];         
		
      	info('playbackStateResponse','ITEM ALBUM = '+data.body['item']['album']['name']);
      	_item_album = data.body['item']['album']['name'];   
        
      	info('playbackStateResponse','ITEM IMAGE = '+data.body['item']['album']['images']['0']['url']);
      	_item_image = data.body['item']['album']['images']['0']['url'];  

    }
    
  	if( data.body['context'] ) {
          
  		info('playbackStateResponse','CONTEXT TYPE = '+data.body['context']['type']);
      	_context_type = data.body['context']['type'];  
        
      	info('playbackStateResponse','CONTEXT URI = '+data.body['context']['uri']);
      	_context_uri = data.body['context']['uri'];    
        
	} 
  
  	if (	item_id[_index] != _item_id 
      ||	item_title[_index] != _item_title 
      || 	item_artist[_index] != _item_artist 
      || 	item_album[_index] != _item_album 
      || 	item_image[_index] != _item_image 
      || 	context_type[_index] != _context_type 
      || 	context_uri[_index] != _context_uri 
      ) {
  
		var _url = _itemcallback;

        _url = _url.replace('#APIKEY#', _apikey);
        info('playbackStateResponse','API KEY = '+_apikey);

        _url = _url.replace('#ID#', _commands[_index].id);
        info('playbackStateResponse','ID = '+_commands[_index].id);

        item_id[_index] = _item_id;
        _url = _url.replace('#ITEM_ID#', encodeURIComponent(item_id[_index]));
        info('playbackStateResponse','ITEM ID = '+_item_id);

        item_title[_index] = _item_title;
        _url = _url.replace('#ITEM_TITLE#', encodeURIComponent(item_title[_index]));
        info('playbackStateResponse','ITEM TITLE = '+_item_title);

        item_artist[_index] = _item_artist;
        _url = _url.replace('#ITEM_ARTIST#', encodeURIComponent(item_artist[_index]));
        info('playbackStateResponse','ITEM ARTIST = '+_item_artist);

        item_album[_index] = _item_album;
        _url = _url.replace('#ITEM_ALBUM#', encodeURIComponent(item_album[_index]));
        info('playbackStateResponse','ITEM ALBUM = '+_item_album);

        item_image[_index] = _item_image;
        _url = _url.replace('#ITEM_IMAGE#', encodeURIComponent(item_image[_index]));
        info('playbackStateResponse','ITEM IMAGE = '+_item_image);

        context_type[_index] = _context_type;
        _url = _url.replace('#CONTEXT_TYPE#', encodeURIComponent(context_type[_index]));
        info('playbackStateResponse','CONTEXT TYPE = '+_context_type);

        context_uri[_index] = _context_uri;
        _url = _url.replace('#CONTEXT_URI#', encodeURIComponent(context_uri[_index]));
        info('playbackStateResponse','CONTEXT URI = '+_context_uri);
      
      	info('playbackStateResponse','STATUS STATE CALLBACK = '+_url);

      	if( protocol == 'HTTP' ) {
        	http.get(_url);
        } else {
          	https.get(_url);
        }
      
    }
  
  	debug('playbackStateResponse','END');  

}

// ======================================
// === CURRENT SHUFFLE STATE RESPONSE ===
// ======================================

function shuffleStateResponse( _index, data) {
	
	var _shuffle_state = false;

	debug('shuffleStateResponse','BEGIN');  
	
    info('shuffleStateResponse', '<< ' + JSON.stringify(data));
  
  	if ( data.body['shuffle_state'] ) {

		_shuffle_state = data.body['shuffle_state'];
     
    }
 
  	if( shuffle_state[_index] != _shuffle_state ) {
        
        var _url = _shufflecallback;

        _url = _url.replace('#APIKEY#', _apikey);
        info('shuffleStateResponse','API KEY = '+_apikey);
        
        _url = _url.replace('#ID#', _commands[_index].id);
        info('shuffleStateResponse','ID = '+_commands[_index].id);

        shuffle_state[_index] = _shuffle_state;
		_url = _url.replace('#STATE#', _shuffle_state);
        info('shuffleStateResponse','STATE = '+_shuffle_state);

        info('shuffleStateResponse','SHUFFLE STATE CALLBACK = '+_url);
      
        if( protocol == 'HTTP' ) {
            http.get(_url);
        } else {
          	https.get(_url);
        }
        
    }
  
  	debug('shuffleStateResponse','END');  

}

// ==============================
// === STATUS POLLING REQUEST ===
// ==============================

function statusPollingRequest( i ) {
  
  	debug('statusPollingRequest','BEGIN');  
  
  	var spotifyApi = new SpotifyWebApi();
    
    spotifyApi.setAccessToken(access[i]);
  
  	info('statusPollingRequest','>> getMyCurrentPlaybackState()');  
  
    spotifyApi.getMyCurrentPlaybackState({}).then( function(data) {
      
      	debug('statusPollingRequest','PROCESS PLAYBACK STATE');
      	playbackStateResponse( i, data);
      	
      	debug('statusPollingRequest','PROCESS SHUFFLE STATE');
    	shuffleStateResponse( i, data);
      
    });
  	
  	debug('statusPollingRequest','END');  
  
}

// =========================
// === PLAYLIST RESPONSE ===
// =========================

function playlistResponse( _index, data) {
	
  	var _playlist_id = '';
    var _playlist_name = '';

    var separator = '';

	debug('playlistResponse','BEGIN'); 
  
 	info('playlistResponse', '<< ' + JSON.stringify(data));
  
  	for ( var i = 0; i < data.body['items'].length; i++) {

      	_uri = data.body['items'][i]['owner']['uri'] + ':' + data.body['items'][i]['type'] + ':' + data.body['items'][i]['id'];                    
      	_playlist_id = _playlist_id + separator + _uri;
      	_playlist_name = _playlist_name + separator + data.body['items'][i]['name'];
       
        separator = '|';

 	}  
  
  	if( playlist_id[_index] != _playlist_id || playlist_name[_index] != _playlist_name ) {

    	var _url = _playlistcallback;

        _url = _url.replace('#APIKEY#', _apikey);
        info('playlistResponse','API KEY '+_apikey);

        _url = _url.replace('#ID#', _commands[_index].id);
        info('playlistResponse','ID = '+_commands[_index].id);

        playlist_id[_index] = _playlist_id;
        _url = _url.replace('#PLAYLIST_ID#', encodeURIComponent(playlist_id[_index]));
        info('playlistResponse','PLAYLIST ID = '+_playlist_id);

        playlist_name[_index] = _playlist_name;
        _url = _url.replace('#PLAYLIST_NAME#', encodeURIComponent(playlist_name[_index]));
        info('playlistResponse','PLAYLIST NAME = '+_playlist_name);

      	info('playlistResponse','PLAYLIST CALLBACK = '+_url);
      
        if( protocol == 'HTTP' ) {
        	http.get(_url);
        } else {
          	https.get(_url);
        }

	} 
      
  	debug('playlistResponse','END'); 
  
}
  
// ================================
// === PLAYLIST POLLING REQUEST ===
// ================================

function playlistPollingRequest( i ) {
  
	debug('playlistPollingRequest','BEGIN'); 
  
  	var spotifyApi = new SpotifyWebApi();
    
    spotifyApi.setAccessToken(access[i]);
  
  	info('playlistPollingRequest','>> getUserPlaylists()');  
  
    spotifyApi.getUserPlaylists().then( function(data) {
      
    	debug('playlistPollingRequest','PROCESS PLAYLIST');
      	playlistResponse( i, data);
      
    });
   
  	debug('playlistPollingRequest','END');  
  
}

// =======================
// === DEVICE RESPONSE ===
// =======================

function deviceResponse( _index, data) {
	
	var _device_id = '';
    var _device_is_active = '';
    var  _device_name = '';
    var _device_type = '';
    var _device_volume = '';

    var separator = '';
  
  	debug('deviceResponse','BEGIN'); 
  	
	info('deviceResponse', '<< ' + JSON.stringify(data));
  
    for ( var i = 0; i < data.body['devices'].length; i++) {

    	_device_id = _device_id + separator + data.body['devices'][i]['id'];
        _device_is_active = _device_is_active + separator + data.body['devices'][i]['is_active'];
      	_device_name = _device_name + separator + data.body['devices'][i]['name'];
      	_device_type = _device_type + separator + data.body['devices'][i]['type'];
      	_device_volume = _device_volume + separator + data.body['devices'][i]['volume_percent'];

        separator = '|';

    }
  
	if(  	device_id[_index] != _device_id 
      || 	device_is_active[_index] != _device_is_active 
      || 	device_name[_index] != _device_name 
      || 	device_type[_index] != _device_type 
      || 	device_volume[_index] != _device_volume 
      ) {

		var _url = _devicecallback;

        _url = _url.replace('#APIKEY#', _apikey);
        info('deviceResponse','API KEY = '+_apikey);

        _url = _url.replace('#ID#', _commands[_index].id);
        info('deviceResponse','ID = '+_commands[_index].id);

        device_id[_index] = _device_id;
        _url = _url.replace('#DEVICE_ID#', encodeURIComponent(device_id[_index]));
        info('deviceResponse','DEVICE ID = '+_device_id);

        device_is_active[_index] = _device_is_active;
        _url = _url.replace('#DEVICE_IS_ACTIVE#', encodeURIComponent(device_is_active[_index]));
        info('deviceResponse','DEVICE IS ACTIVE = '+_device_is_active);

        device_name[_index] = _device_name;
        _url = _url.replace('#DEVICE_NAME#', encodeURIComponent(device_name[_index]));
        info('deviceResponse','DEVICE NAME = '+_device_name);

        device_type[_index] = _device_type;
        _url = _url.replace('#DEVICE_TYPE#', encodeURIComponent(device_type[_index]));
        info('deviceResponse','DEVICE TYPE = '+_device_type);

        device_volume[_index] = _device_volume;
        _url = _url.replace('#DEVICE_VOLUME#', encodeURIComponent(device_volume[_index]));
        info('deviceResponse','DEVICE VOLUME = '+_device_volume);

      	info('deviceResponse','DEVICE CALLBACK = '+_url);
      
        if( protocol == 'HTTP' ) {
        	http.get(_url);
        } else {
          	https.get(_url);
        }
      
    }
  
	debug('deviceResponse','END');  
  
}

  
// ==============================
// === DEVICE POLLING REQUEST ===
// ==============================

function devicePollingRequest( i ) {
  
	debug('devicePollingRequest','BEGIN');  
  
  	var spotifyApi = new SpotifyWebApi();
    
    spotifyApi.setAccessToken(access[i]);
  
  	info('devicePollingRequest','>> getUserPlaylists()');  
  
    spotifyApi.getMyDevices().then( function(data) {
      
      	debug('devicePollingRequest','PROCESS DEVICE');
      	deviceResponse( i, data);
      
    });
  	
  	debug('devicePollingRequest','END');  
  
}
  
  
// ==============================
// === REFRESH TOKEN RESPONSE ===
// ==============================

function refreshTokenResponse( json) {

	debug('refreshTokenResponse','BEGIN');  
 
  	var _data = JSON.parse(json);  
	info('refreshTokenResponse','<< ' + JSON.stringify(_data));
  
  	var i = _data['result']['i'];
	debug('refreshTokenResponse','I = '+ i);

  	access[ i ] = _data['result']['access'];
  	debug('refreshTokenResponse','ACCESS = '+access[ i ]);

  	refresh[ i ] = _data['result']['refresh'];
  	debug('refreshTokenResponse','REFRESH = '+refresh[ i ]);

  	expire[ i ] = _data['result']['expire'];
  	debug('refreshTokenResponse','EXPIRE = '+expire[ i ]);

    counter[i] = 0;
	debug('refreshTokenResponse','COUNTER = '+counter[ i ]);
  
  	debug('refreshTokenResponse','END');  

}

// =============================
// === REFRESH TOKEN REQUEST ===
// =============================

function refreshTokenRequest( i ) {

  	debug('refreshTokenRequest','BEGIN');  
  
  	var _url = _refreshcallback;
  
  	_url = _url.replace('#APIKEY#', _apikey);
    debug('refreshTokenRequest','APIKEY = '+_apikey);

 	_url = _url.replace('#I#', i);
    debug('refreshTokenRequest','I = '+i);

	_url = _url.replace('#ID#', _commands[i].id);
	debug('refreshTokenRequest','ID = '+_commands[i].id);  
  
  	info('refreshTokenRequest','>> ' + _url);
  
	if( protocol == 'HTTP' ) {

    	http.get( _url, (resp) => { 
        
        	let data = '';
        
          	resp.on('data', (chunk) => {
          		data += chunk;
        	} );
        
          	resp.on('end', () => {
				refreshTokenResponse( data);
        	} );
          
		}).on("error", (err) => {
			error('refreshTokenRequest','Failed to http refresh token ' + err);
  	  	});

    } else {

    	http.get( _url, (resp) => { 
        
        	let data = '';
        
          	resp.on('data', (chunk) => {
          		data += chunk;
        	} );
        
          	resp.on('end', () => {
				refreshTokenResponse( data);
        	} );
          
		}).on("error", (err) => {
			error('refreshTokenRequest','Failed to https refresh token ' + err);
  	  	});
      
	}
  
    debug('refreshTokenRequest','END');  
  
}

// ====================
// === SPOTIFY LOOP ===
// ====================

function spotifyLoop() {

	debug('spotifyLoop','========================================== LOOP ================================================================');  
  	// debug('spotifyLoop','BEGIN');  
  
  	var _expire = Math.floor(new Date()/1000);
	
	if ( _started == true) {

		for ( var i = 0; i < _commands.length; i++) {
            
          	debug('spotifyLoop','CURRENT TIME (' + i + ')= '+_expire);
          	debug('spotifyLoop','EXPIRE TIME (' + i + ')= '+expire[i]);
          
          	if( expire[i] === undefined || expire[i] <= _expire) {
              
              	debug('spotifyLoop','REFRESH ACCOUNT TOKEN ('+i+')');
            	refreshTokenRequest( i );
             
            }
            
            if( access[i] !== undefined ) {
            
            	/* debug('spotifyLoop','COUNTER['+i+'] = ' + counter[i]); */
              
            	debug('spotifyLoop','STATUS POLLING ACCOUNT ('+i+')');
          		statusPollingRequest(i);
              
          		/*if( counter[i] !== undefined && counter[i] == 0 ) {

            		debug('spotifyLoop','DEVICE POLLING ACCOUNT ('+i+')');
            		devicePollingRequest(i);

            		debug('spotifyLoop','CONFIG POLLING ACCOUNT ('+i+')');                  
            		playlistPollingRequest(i);

          		}

          		counter[i]++; if( counter[i] >= count ) { counter[i] = 0 } */
              
            }

		}
  	  
    } else {
      
      	debug('spotifyLoop',"WAITING FOR CONFIGURATION");
  	
    }
  
  	// debug('spotifyLoop','END');
  	
}

// ============
// === MAIN ===
// ============

debug('spotify','BEGIN');  

setInterval( spotifyLoop, delay);

configRequest();

debug('spotify','END');