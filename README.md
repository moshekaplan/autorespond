# emailsubscribe
Allows subscribing/unsubscribing to phpList via email

Partially based on autorespond from http://web.archive.org/web/20090214025932/http://www.sawey.be/forum/filebase.php?d=1&id=14&c_old=4&what=c

##Installation:##
Copy `emailsubscribe.php` to `lists`

Configure your mail forwarding agent to call `emailsubscribe.php` when an email is sent to the address that will handle subscribe/unsubscribe.

Ensure PHP's [mailparse](php.net/manual/en/book.mailparse.php) is available so that the [PHP Mime Mail Parser](https://code.google.com/p/php-mime-mail-parser/) (included) can operate.


##Instructions:##

To subscribe:
Send an email that contains the word 'subscribe' in the subject or body

To unsubscribe:
Send an email that contains the word 'unsubscribe' in the subject or body



