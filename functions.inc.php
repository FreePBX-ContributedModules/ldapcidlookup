<?php /* $Id */
//Copyright (C) 2006 WeBRainstorm S.r.l. (ask@webrainstorm.it)
//
//This program is free software; you can redistribute it and/or
//modify it under the terms of the GNU General Public License
//as published by the Free Software Foundation; either version 2
//of the License, or (at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.

function ldapcidlookup_hook_core($viewing_itemid, $target_menuid) {
	// TODO: add option to avoid callerid lookup if the telco already supply a callerid name (Overwrite checkbox ? )
	$html = '';
	if ($target_menuid == 'did')	{
		$html = '<tr><td colspan="2"><h5>';
		$html .= _("CID Lookup Source");
		$html .= '<hr></h5></td></tr>';
		$html .= '<tr>';
		$html .= '<td><a href="#" class="info">';
		$html .= _("Source").'<span>'._("Sources can be added in Caller Name Lookup Sources section").'.</span></a>:</td>';
		$html .= '<td><select name="cidlookup_id">';
		$sources = cidlookup_list();
		$current = cidlookup_did_get($viewing_itemid);
		foreach ($sources as $source)
			$html .= sprintf('<option value="%d" %s>%s</option>', $source['cidlookup_id'], ($current == $source['cidlookup_id']?'selected':''), $source['description']);
		$html .= '</select></td></tr>';
/* 
		// Not yet fully implemented
		$html .= '<tr>';
		$html .= '<td><a href="#" class="info">';
		$html .= _("Overwrite Caller Name").'<span>'._("This option let the source specified overwrite the caller name if already supplied from telco").'.</span></a>:</td>';
		$html .= '<td><input type="checkbox" name="overwrite" value="1"></td>';
		$html .= '</tr>';
*/

	}

	return $html;
	
}

function ldapcidlookup_hookProcess_core($viewing_itemid, $request) {
	
	// TODO: move sql to functions cidlookup_did_(add, del, edit)
	if (!isset($request['action']))
		return;
	switch ($request['action'])	{
		case 'addIncoming':
			$results = sql(sprintf('INSERT INTO cidlookup_incoming (cidlookup_id, extension, cidnum, channel) VALUES ("%d", "%s", "%s", "%s")', 
				$request['cidlookup_id'], $request['extension'], $request['cidnum'], $request['channel']));
		break;
		case 'delIncoming':
			$extarray = explode('/', $request['extdisplay'], 3);
			$results = sql(sprintf("DELETE FROM cidlookup_incoming WHERE extension = '%s' AND cidnum = '%s' AND channel = '%s'",
				$extarray[0], $extarray[1], $extarray[2]));
		break;
		case 'edtIncoming':	// deleting and adding as in core module
			$extarray = explode('/', $request['extdisplay'], 3);
			$results = sql(sprintf("DELETE FROM cidlookup_incoming WHERE extension = '%s' AND cidnum = '%s' AND channel = '%s'",
				$extarray[0], $extarray[1], $extarray[2]));
			$results = sql(sprintf('INSERT INTO cidlookup_incoming (cidlookup_id, extension, cidnum, channel) VALUES ("%d", "%s", "%s", "%s")', 
				$request['cidlookup_id'], $request['extension'], $request['cidnum'], $request['channel']));
		break;
	}
}


function ldapcidlookup_hookGet_config($engine) {
	// TODO: integrating with direct extension <-> DID association
	// TODO: add option to avoid callerid lookup if the telco already supply a callerid name (GosubIf)
	global $ext;  // is this the best way to pass this?
	switch($engine) {
		case "asterisk":
			$pairing = cidlookup_did_list();
			if(is_array($pairing)) {
				foreach($pairing as $item) {
					if ($item['cidlookup_id'] != 0) {

						// Code from modules/core/functions.inc.php core_get_config inbound routes
						$exten = $item['extension'];
						$cidnum = $item['cidnum'];
						$channel = $item['channel'];
						
						$exten = (empty($exten)?"s":$exten);
						$exten = $exten.(empty($cidnum)?"":"/".$cidnum); //if a CID num is defined, add it

						if (empty($channel))
							$context = "ext-did";
						else 
							$context = "macro-from-zaptel-{$channel}";

						$ext->splice($context, $exten, 1, new ext_gosub('1', 'cidlookup_'.$item['cidlookup_id'], 'cidlookup'));
//						$ext->splice($context, $exten, 2, new ext_gosub('1', 's', 'inbound-caller-notification'));
					
					}
				}
			}
		break;
	}

}

/*

// 	Generates dialplan for cidlookup
//	We call this with retrieve_conf

*/

function cidlookup_get_config($engine) {
	// TODO: discuss if mysql and http lookup should be implemented in dialplan or in an external AGI
	global $ext;  // is this the best way to pass this?
	global $asterisk_conf;
	switch($engine) {
		case "asterisk":
			$sources = cidlookup_list();
			if(is_array($sources)) {
				foreach($sources as $item) {

					// Search for number in the cache, if found lookupcidnum and return
					if ($item['cidlookup_id'] != 0)	{
						if ($item['cache'] == 1 && $item['sourcetype'] != 'internal') {
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_gotoif('$[${DB_EXISTS(cidname/${CALLERID(num)})} = 1]', 'cidlookup,cidlookup_return,1'));
						}
					}

					switch($item['sourcetype']) {

						case "internal":
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_lookupcidname(''));
						break;

						case "enum":
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_txtcidname('${CALLERID(num)}'));
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_setvar('CALLERID(name)', '${TXTCIDNAME}'));
						break;

						case "http":
							if (!empty($item['http_username']) && !empty($item['http_password']))
								$auth = sprintf('%s:%s@', $item['http_username'], $item['http_password']);
							else
								$auth = '';
								
							if (!empty($item['http_port']))
								$host = sprintf('%s:%d', $item['http_host'], $item['http_port']);
							else
								$host = $item['http_host'].':80';

							if (substr($item['http_path'], 0, 1) == '/')
								$path = substr($item['http_path'], 1);
							else
								$path = $item['http_path'];
								
							$query = str_replace('[NUMBER]', '${CALLERID(num)}', $item['http_query']);
							$url = sprintf('http://%s%s/%s?%s', $auth, $host, $path, $query);
							$curl = sprintf('${CURL(%s)}', $url);
							
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_setvar('CALLERID(name)', $curl));
						break;

						case "mysql":
							//Escaping MySQL query - thanks to http://www.asteriskgui.com/index.php?get=utilities-mysqlscape

							$replacements = array (
							  	'\\' => '\\\\',
							  	'"' => '\\"',
							  	'\'' => '\\\'',
							  	' ' => '\\ ',
							  	',' => '\\,',
							  	'(' => '\\(',
							  	')' => '\\)',
							  	'.' => '\\.',
							  	'|' => '\\|'
							);
							
							$query = str_replace(array_keys($replacements), array_values($replacements), $item['mysql_query']);
							$query = str_replace('[NUMBER]', '${CALLERID(num)}', $query);

							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_mysql_connect('connid', $item['mysql_host'],  $item['mysql_username'],  $item['mysql_password'],  $item['mysql_dbname']));							
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_mysql_query('resultid', 'connid', $query));
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_mysql_fetch('fetchid', 'resultid', 'CALLERID(name)'));
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_mysql_clear('resultid'));							
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_mysql_disconnect('connid'));
						break;

						// TODO: implement SugarCRM lookup, look at code snippet at http://nerdvittles.com/index.php?p=82
						case "sugarcrm":
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_noop('SugarCRM not yet implemented'));
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_return(''));
						case "ldap":
							$newbase = str_replace(',', '@', $item['ldap_base']);
							$newformat = str_replace(' ', '@', $item['ldap_format']);
							$lookup = new ext_agi('ldapcidlookup.agi');
							$lookup->data .= ',HOST='.$item['ldap_host'];
							$lookup->data .= ',USER='.$item['ldap_username'];
							$lookup->data .= ',PASS='.$item['ldap_password'];
							$lookup->data .= ',AREA='.$item['ldap_prefix'];
							$lookup->data .= ',FORMAT='.$newformat;
							$lookup->data .= ',DN='.$newbase;
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', $lookup);
						break;
					}

					// Put numbers in the cache
					if ($item['cidlookup_id'] != 0)	{
						if ($item['cache'] == 1 && $item['sourcetype'] != 'internal') {
							$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_db_put('cidname', '${CALLERID(num)}', '${CALLERID(name)}' ));
						}
						$ext->add('cidlookup', 'cidlookup_'.$item['cidlookup_id'], '', new ext_return(''));
					}
				}

				$ext->add('cidlookup', 'cidlookup_return', '', new ext_lookupcidname(''));
				$ext->add('cidlookup', 'cidlookup_return', '', new ext_return(''));
			}
		break;
	}
}


function cidlookup_did_get($did){
	$extarray = explode('/', $did, 3);
	if(count($extarray) == 3)	{ // differentiate beetween '//' (Any did / any cid and '' empty string)
		$sql = sprintf("SELECT cidlookup_id FROM cidlookup_incoming WHERE extension = '%s' AND cidnum = '%s' AND channel = '%s'", $extarray[0], $extarray[1], $extarray[2]);
		$result = sql($sql, "getRow", DB_FETCHMODE_ASSOC);
		if(is_array($result)){
			return $result['cidlookup_id'];
		} else
			return null;
	} else { // $did is an empty string (for example when adding a new did)
		return 0;
	}
}

function cidlookup_did_list() {
	$results = sql("SELECT * FROM cidlookup_incoming","getAll",DB_FETCHMODE_ASSOC);
	return is_array($results)?$results:null;
}

function cidlookup_list() {
	// TODO: discuss department isolation of sources
	$allowed = array(array('cidlookup_id' => 0, 'description' => _("None"), 'sourcetype' => null));
	$results = sql("SELECT * FROM cidlookup","getAll",DB_FETCHMODE_ASSOC);
	if(is_array($results)){
		foreach($results as $result){
			// check to see if we have a dept match for the current AMP User.
			if (checkDept($result['deptname'])){
				// return this item
				$allowed[] = $result;
			}
		}
	}
	return isset($allowed)?$allowed:null;
}

function cidlookup_get($id){
	$results = sql("SELECT * FROM cidlookup WHERE cidlookup_id = '$id'","getRow",DB_FETCHMODE_ASSOC);
	return isset($results)?$results:null;
}

function cidlookup_del($id){
	// Deleting source and its associations
	$results = sql("DELETE FROM cidlookup WHERE cidlookup_id = '$id'","query");
	$results = sql("DELETE FROM cidlookup_incoming WHERE cidlookup_id = '$id'","query");
}

function cidlookup_add($post){
	if(!cidlookup_chk($post))
		return false;
	extract($post);
	if (!isset($cache))
		$cache = 0;
	$results = sql("
		INSERT INTO cidlookup
			(description, sourcetype, cache, deptname, http_host, http_port, http_username, http_password, http_path, http_query, mysql_host, mysql_dbname, mysql_query, mysql_username, mysql_password,ldap_host,ldap_username,ldap_password,ldap_base,ldap_prefix,ldap_format)
		VALUES 
			(\"$description\", \"$sourcetype\", \"$cache\", \"$deptname\", \"$http_host\", \"$http_port\", \"$http_username\", \"$http_password\", \"$http_path\", \"$http_query\", \"$mysql_host\", \"$mysql_dbname\", \"$mysql_query\", \"$mysql_username\", \"$mysql_password\",\"$ldap_host\",\"$ldap_username\",\"$ldap_password\",\"$ldap_base\",\"$ldap_prefix\",\"$ldap_format\")
		");
}

function cidlookup_edit($id,$post){
	if(!cidlookup_chk($post))
		return false;
	extract($post);
	if ($cache != 1)
		$cache = 0;
	$results = sql("
		UPDATE cidlookup 
		SET 
			description = \"$description\", 
			deptname = \"$deptname\", 
			sourcetype = \"$sourcetype\" ,
			cache = \"$cache\",
			http_host = \"$http_host\",
			http_port = \"$http_port\",
			http_username = \"$http_username\",
			http_password = \"$http_password\",
			http_path = \"$http_path\",
			http_query = \"$http_query\",
			mysql_host = \"$mysql_host\",
			mysql_dbname = \"$mysql_dbname\",
			mysql_query = \"$mysql_query\",
			mysql_username = \"$mysql_username\",
			mysql_password  = \"$mysql_password\",
			ldap_host = \"$ldap_host\",
			ldap_username = \"$ldap_username\",
			ldap_password = \"$ldap_password\",
			ldap_base = \"$ldap_base\",
			ldap_prefix = \"$ldap_prefix\",
			ldap_format = \"$ldap_format\"
		WHERE cidlookup_id = \"$id\"");
}

// ensures post vars is valid
function cidlookup_chk($post){
	// TODO: Add sanity checks on $_POST
	return true;
}
?>
