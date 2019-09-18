<?php

define('LH_LIB_ROOT', '/Users/user/MyData/phplib');
define('LH_SESSION_DIR', '/Users/user/MyData/lhsessiondata/');
define('LH_SESSION_LOG_SESSION', true);
define('LH_MIN_HIT_RATIO_AIML', 80);
define('LH_MIN_HIT_RATIO_CSML', 50);
date_default_timezone_set('UTC');

require_once LH_LIB_ROOT . '/lhChatterBoxDataProviders/classes/lhAIML.php';
require_once LH_LIB_ROOT . '/lhChatterBoxDataProviders/classes/lhCSML.php';
require_once LH_LIB_ROOT . '/lhChatterBoxDataProviders/classes/lhSessionFile.php';
require_once LH_LIB_ROOT . '/lhValidator/classes/lhNameValidator.php';
require_once LH_LIB_ROOT . '/lhValidator/classes/lhFullNameValidator.php';
require_once LH_LIB_ROOT . '/lhRuNames/classes/lhRuNames.php';

require_once 'lhChatterBox20/classes/lhChatterBox.php';

$cb = new lhChatterBox(
        new lhSessionFile('test_session_' . rand()), 
        $ai = new lhAIML('aiml.xml'),
        $cs = new lhCSML('csml.xml')
);
$not_found_blocks = $cs->csmlCheck();

if (count($not_found_blocks)) {
    echo "Не найдены блоки:\n";
    print_r($not_found_blocks);
}

$answer = $cb->scriptStart("start");
echo "$answer[text]\n# ";
while (true) {
    $user_said = trim(fread(STDIN, 256));

    if (preg_match("/^\/(\w+)/", $user_said, $matches)) {
        $answer = $cb->scriptStart($matches[1]);
    } else {
        $answer = $cb->process($user_said);
    }
    
    if ( $answer !== false ) {
        echo "$answer[text]\n# ";
    }
    
}



