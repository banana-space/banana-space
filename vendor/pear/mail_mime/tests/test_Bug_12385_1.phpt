--TEST--
Bug #12385  Bad regex when replacing css style attachments
--SKIPIF--
--FILE--
<?php
$from='user@from.example.com';

require_once('Mail/mime.php');

$mime=new Mail_mime();

$body="<style>
className {
    background-image: url('test.gif');
}
</script>
";

$mime->setHTMLBody($body);
$mime->setFrom($from);
$mime->addHTMLImage('','image/gif', 'test.gif', false);
$msg = $mime->get();

$cidtag = preg_match("|url\('cid:[^']*'\);|", $msg);
if (!$cidtag){
    print("FAIL:\n");
    print($msg);
}else{
    print("OK");
}
--EXPECT--
OK
