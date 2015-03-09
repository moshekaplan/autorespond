#!/usr/php/54/usr/bin/php -qc/home1/rushroad/public_html/php.ini
<?php
//echo "Filename is:".__FILE__."\n";
error_reporting(-1);
$data = '';
$sock = fopen('php://stdin', 'r');
while (!feof($sock)){ 
    $data .= fread($sock, 1024);
}
fclose($sock);
// Log it, JIC
file_put_contents("email.txt", $data);

ob_start();
//$er = error_reporting(0);
require_once dirname(__FILE__) .'/admin/commonlib/lib/unregister_globals.php';
require_once dirname(__FILE__) .'/admin/commonlib/lib/magic_quotes.php';

## none of our parameters can contain html for now
$_GET = removeXss($_GET);
$_POST = removeXss($_POST);
$_REQUEST = removeXss($_REQUEST);
$_SERVER = removeXss($_SERVER);
$_COOKIE = removeXss($_COOKIE);

## remove a trailing punctuation mark on the uid
if (isset($_GET['uid'])) {
  if (preg_match('/[\.,:;]$/',$_GET['uid'])) {
    $_GET['uid'] = preg_replace('/[\.,:;]$/','',$_GET['uid']);
  }
}

if (isset($_SERVER["ConfigFile"]) && is_file($_SERVER["ConfigFile"])) {
  include $_SERVER["ConfigFile"];
} elseif (is_file("config/config.php")) {
  include 'config/config.php';
} else {
  print "Error, cannot find config file\n";
  exit;
}

require_once dirname(__FILE__).'/admin/init.php';

   
$GLOBALS["database_module"] = basename($GLOBALS["database_module"]);
$GLOBALS["language_module"] = basename($GLOBALS["language_module"]);

require_once dirname(__FILE__).'/admin/'.$GLOBALS["database_module"];

# load default english and language
include_once dirname(__FILE__)."/texts/english.inc";
if (is_file(dirname(__FILE__).'/texts/'.$GLOBALS["language_module"])) {
  include_once dirname(__FILE__).'/texts/'.$GLOBALS["language_module"];
}
# Allow customisation per installation
if (is_file($_SERVER['DOCUMENT_ROOT'].'/'.$GLOBALS["language_module"])) {
  include_once $_SERVER['DOCUMENT_ROOT'].'/'.$GLOBALS["language_module"];
}

include_once dirname(__FILE__)."/admin/languages.php";
require_once dirname(__FILE__)."/admin/defaultconfig.php";

require_once dirname(__FILE__).'/admin/connect.php';
include_once dirname(__FILE__)."/admin/lib.php";

$I18N = new phplist_I18N();
header('Access-Control-Allow-Origin: '.ACCESS_CONTROL_ALLOW_ORIGIN);

if (!empty($GLOBALS["SessionTableName"])) {
  require_once dirname(__FILE__).'/admin/sessionlib.php';
}
@session_start(); # it may have been started already in languages

if (!isset($_POST) && isset($HTTP_POST_VARS)) {
  require 'admin/commonlib/lib/oldphp_vars.php';
}


$listid = 3;

    
// https://code.google.com/p/php-mime-mail-parser/
require_once('MimeMailParser.class.php');

//$fd = fopen("php://stdin", "r");
//$Parser->setStream($fd);
//$path = 'email.txt';
//$Parser->setPath($path);
//$email = file_get_contents('email.txt');



$Parser = new MimeMailParser();
$Parser->setText($data);

$email = $Parser->getHeader('from');
$subject = $Parser->getHeader('subject');
$text = $Parser->getMessageBody('text');

//echo "Email is: '".$email."'\n";

$action = '';
if (strpos(strtoupper($subject), "UNSUBSCRIBE") !== false || strpos(strtoupper($text), "UNSUBSCRIBE") !== false){
    $action = "unsubscribe";
}
else if (strpos(strtoupper($subject), "SUBSCRIBE") !== false || strpos(strtoupper($text), "SUBSCRIBE") !== false){
    $action = "subscribe";
}
else{
    sendMail($email, "Error", "Either send a message with 'subscribe' or 'unsubscribe' in it.", system_messageheaders($email));
}

function subscribeEmail($email, $listid){
    $userid = 0;
    
    // Register the email address
    $result = Sql_query(sprintf('select * from %s where email = "%s"',$GLOBALS["tables"]["user"],sql_escape($email)));
    // If the email address was already registered
    if (Sql_affected_rows()) {
        //echo "Already registered, setting to confirmed\n";
        $result = Sql_fetch_array($result);
        $userid = $result["id"];
        // In case the user had been blacklisted - From admin\commonlib\lib\userlib.php: function unBlackList
        Sql_Query(sprintf('delete from %s where email = "%s"', $GLOBALS["tables"]["user_blacklist"],$email));
        Sql_Query(sprintf('delete from %s where email = "%s"', $GLOBALS["tables"]["user_blacklist_data"],$email));
        Sql_Query(sprintf('update %s set blacklisted = 0 where id = %d',$GLOBALS["tables"]["user"],$userid));
    }
    # they do not exist, so add them
    else {
        //echo "Not registered, inserting\n";
        $htmlemail = 0;
        $query = sprintf('insert into %s (email,entered,uniqid,confirmed,
        htmlemail,subscribepage) values("%s",current_timestamp,"%s",1,%d,%d)',
        $GLOBALS["tables"]["user"],sql_escape($email),getUniqid(),$htmlemail,$listid);
        $result = Sql_query($query);
        $userid = Sql_Insert_Id($GLOBALS['tables']['user'], 'id');
        addSubscriberStatistics('total users',1);
    }
    // Now subscribe the email to the list
    $listid = sprintf('%d',$listid);
    if (!empty($listid)) {
        //echo "Adding user to list\n";
        $result = Sql_query(sprintf('replace into %s (userid,listid,entered) values(%d,%d,now())',$GLOBALS["tables"]["listuser"],$userid,$listid));
        addSubscriberStatistics('subscribe',1,$listid);
    }
    sendMail($email, "Subscribed", "You have been subscribed", system_messageheaders($email));
}

function unsubscribeEmail($email){
    // Unsubscribes the user from all lists

    // Look up the user's ID
    $query = Sql_Fetch_Row_Query("SELECT id FROM " . $GLOBALS["tables"]["user"] . " WHERE email = '" . sql_escape($email) . "'");
    if(isset($query[0])){
        $userid = $query[0];
        // Unsubscribe the User from all lists
        //echo "Unsubscribing the user from all lists!\n";
        $result = Sql_query("DELETE FROM " . $GLOBALS["tables"]["listuser"] . " WHERE userid = '" . $userid . "'");
        // Add user to blacklist
        addUserToBlacklist($email,"Unsubscribed by mail");
        addUserHistory($email,"Unsubscription","Unsubscribed from all lists");
        sendMail($email, "Unsubscribed", "You have been unsubscribed", system_messageheaders($email));
        return True;
    }
    //echo "Unable to find user, so cannot unsubscribe!\n";
    sendMail($email, "Error", "You were not subscribed so you could not be unsubscribed", system_messageheaders($email));
    return False;
}

//echo "Action is: ".$action."\n";

switch($action){
    case 'subscribe':
        subscribeEmail($email, $listid);
        break;
    case 'unsubscribe':
        if (!unsubscribeEmail($email)){
            //send an email to tell we didn't receive a good uniqid
            //sendError($email, system_messageheaders(), $envelope, $error_subject_invalid_unid);
        }
        break;
    default:
        break;
}
//echo "Work complete\n";
?>