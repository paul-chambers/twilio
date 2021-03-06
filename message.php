<?php
require 'PHPMailer/PHPMailerAutoload.php';

/*
    config.inc.php must define:

    $messageHost  =  hostname of our email->MMS gateway
                     (string)
    $emailMap     =  maps E.164 phone number to destination email address
                     (associative array, string -> string)
    $forwardMap   =  maps email address to an array of E.164 phone numbers
                     to forward the message to
                     (associative array, string -> array(strings))
*/
include 'config.inc.php';

header('Content-type: text/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';

$from       = $_REQUEST['From'];
$to         = $_REQUEST['To'];
$body       = $_REQUEST['Body'];
$msgSid     = $_REQUEST['MessageSid'];
$mediaCount = $_REQUEST['NumMedia'];

$fromName   = "(".substr($from,2,3).") ".substr($from,5,3)."-".substr($from,8);
$toName     = "(".substr($to,2,3).") ".substr($to,5,3)."-".substr($to,8);

$msgType    = ($mediaCount != 0) ? "MMS" : "SMS";

$extensionMap = array(
	"image/jpeg" => "jpg",
	"image/png"  => "png",
	"image/gif"  => "gif"
);

$emailTo = $emailMap[ $to ];
$fwdTo   = $forwardMap[ $emailTo ];

/* forward the message to other numbers */
echo '<Response>';

if (!empty($fwdTo))
{
    foreach ($fwdTo as $number)
    {
        echo "<Message to=\"{$number}\">";

        echo "<Body>{$fromName} > {$toName} {$body}</Body>";
        for ($idx = 0; $idx < $mediaCount; ++$idx)
        {
	        echo "<Media>{$_REQUEST['MediaUrl'.$idx]}</Media>";
        }

        echo '</Message>';
    }
}

echo '</Response>';

/* Send the email */

$mail = new PHPMailer;
$mail->isHTML(true);                                  // Set email format to HTML

$mail->setFrom( $from."@".$messageHost, $fromName );
$mail->addAddress( $emailTo );

$mail->Subject = "{$msgType} from {$fromName}";

$msg = "from:&nbsp;<b>{$fromName}</b><br/>to:&nbsp;<b>{$toName}</b><p>{$body}</p>";

for ($idx = 0; $idx < $mediaCount; ++$idx)
{
    $mediaURL = $_REQUEST['MediaUrl'.$idx];
    $mimeType = $_REQUEST['MediaContentType'.$idx];
    $cid      = $_REQUEST['MessageSid'].'-'.$idx;
    $filename = $cid.'.'.$extensionMap[$mimeType];

    $mail->AddStringEmbeddedImage( file_get_contents( $mediaURL ), $cid, $filename, 'base64', $mimeType );

    $msg .= "<img src=\"cid:{$cid}\" /><br/>";
}

$mail->Body = $msg;

if (!$mail->send())
{
    echo 'Message could not be sent.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
}

?>
