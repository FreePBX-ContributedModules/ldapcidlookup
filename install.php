<?php

global $db;
global $amp_conf;

$autoincrement = (($amp_conf["AMPDBENGINE"] == "sqlite") || ($amp_conf["AMPDBENGINE"] == "sqlite3")) ? "AUTOINCREMENT":"AUTO_INCREMENT";

// create the tables
$sql = "CREATE TABLE IF NOT EXISTS cidlookup (
	cidlookup_id INTEGER NOT NULL PRIMARY KEY $autoincrement,
	description varchar(50) NOT NULL,
	sourcetype varchar(100) NOT NULL,
	cache tinyint(1) NOT NULL default '0',
	deptname varchar(30) default NULL,
	http_host varchar(30) default NULL,
	http_port varchar(30) default NULL,
	http_username varchar(30) default NULL,
	http_password varchar(30) default NULL,
	http_path varchar(100) default NULL,
	http_query varchar(100) default NULL,
	mysql_host varchar(60) default NULL,
	mysql_dbname varchar(60) default NULL,
	mysql_query text,
	mysql_username varchar(30) default NULL,
	mysql_password varchar(30) default NULL
);";
$check = $db->query($sql);
if (DB::IsError($check)) {
        die_freepbx( "Can not create `cidlookup` table: " . $check->getMessage() .  "\n");
}


$sql = "CREATE TABLE IF NOT EXISTS cidlookup_incoming (
	cidlookup_id INT NOT NULL,
	extension VARCHAR(50),
	cidnum VARCHAR(30),
	channel VARCHAR(30)
);";
$check = $db->query($sql);
if (DB::IsError($check)) {
        die_freepbx( "Can not create `cidlookup_incomming` table: " . $check->getMessage() .  "\n");
}

// first update
$sql = "SELECT cache FROM cidlookup";
$check = $db->getRow($sql, DB_FETCHMODE_ASSOC);
if (DB::IsError($check)) {
	// add new field
	$sql = "ALTER TABLE cidlookup ADD cache INTEGER NOT NULL DEFAULT 0;";
	$result = $db->query($sql);
	if(DB::IsError($result)) {
		die_freepbx($result->getMessage());
	}
}

// second update
$sql = "SELECT cidlookup from incoming;";
$check = $db->query($sql);
if (DB::IsError($check)) {
	$sql = "ALTER TABLE incoming ADD cidlookup INT(2);";
	$check = $db->query($sql);
	if (DB::IsError($check)) {
		die_freepbx( "Can not alter `incoming` table: " . $check->getMessage() .  "\n");
	}
}

// additional ldap update
$sql = "SELECT ldap_host from cidlookup;";
$check = $db->query($sql);
if (DB::IsError($check)) {
	$sql = "ALTER TABLE cidlookup ADD ldap_host varchar(64);";
	$check = $db->query($sql);
	if (DB::IsError($check)) {
		die_freepbx( "Can not alter `cidlookup` table: " . $check->getMessage() .  "\n");
	}
	$sql = "ALTER TABLE cidlookup ADD ldap_username varchar(64);";
	$check = $db->query($sql);
	if (DB::IsError($check)) {
		die_freepbx( "Can not alter `cidlookup` table: " . $check->getMessage() .  "\n");
	}
	$sql = "ALTER TABLE cidlookup ADD ldap_password varchar(64);";
	$check = $db->query($sql);
	if (DB::IsError($check)) {
		die_freepbx( "Can not alter `cidlookup` table: " . $check->getMessage() .  "\n");
	}
	$sql = "ALTER TABLE cidlookup ADD ldap_base varchar(64);";
	$check = $db->query($sql);
	if (DB::IsError($check)) {
		die_freepbx( "Can not alter `cidlookup` table: " . $check->getMessage() .  "\n");
	}
}

// additional ldap update
$sql = "SELECT ldap_prefix from cidlookup;";
$check = $db->query($sql);
if (DB::IsError($check)) {
	$sql = "ALTER TABLE cidlookup ADD ldap_prefix varchar(8);";
	$check = $db->query($sql);
	if (DB::IsError($check)) {
		die_freepbx( "Can not alter `cidlookup` table: " . $check->getMessage() .  "\n");
	}
}

// additional ldap update
$sql = "SELECT ldap_format from cidlookup;";
$check = $db->query($sql);
if (DB::IsError($check)) {
	$sql = "ALTER TABLE cidlookup ADD ldap_format varchar(32);";
	$check = $db->query($sql);
	if (DB::IsError($check)) {
		die_freepbx( "Can not alter `cidlookup` table: " . $check->getMessage() .  "\n");
	}
}

global $amp_conf;
global $asterisk_conf;

$agibin_source = dirname(__FILE__)."/agi-bin/*";
$agibin_dest = isset($asterisk_conf['astagidir']) ? $asterisk_conf['astagidir']:'/var/lib/asterisk/agi-bin';

exec("cp -rf $agibin_source $agibin_dest 2>&1");


?>

