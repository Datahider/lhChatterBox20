<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of lhChatterBox
 *
 * @author user
 */
require_once __DIR__ . '/../abstract/lhAbstractChatterBox.php';

class lhChatterBox extends lhAbstractChatterBox {
    
    public function process($text) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->log('$text='. $text, 15);
        $this->text = $text;
        $this->session->log(lhAbstractSession::$facility_chat, 'IN', $text);
        switch ($this->session->get('status', 'script')) {
            case 'script':
                $answer = $this->doScript();
                break;
            case 'babbler':
                $answer = $this->doAiml();
                break;
            default:
                $answer = false;
        }
        if ($answer !== false) {
            $this->session->log(lhAbstractSession::$facility_chat, 'OUT', $answer['text']);
        }
        return $answer;
    }
    
    public function scriptStart($block_name=null) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->log('$block_name='. $block_name, 15);
        $this->session->set('status', 'script');
        $this->session->set('script_state', (string)$block_name);

        $block = $this->csml->block($block_name);
        $this->setVars($block);
        $answer = $this->answerFromCsmlBlock($block);
        $this->session->log(lhSessionFile::$facility_chat, 'OUT', $answer['text']);
        return $answer;
    }
    
    private function doScript() {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->csml->start($this->session->get('script_state', 'start'));
        $xml = $this->csml->getCsml();
        $min_hit_ratio = isset($xml['minhit']) ? $xml['minhit'] : 70;
        $answer = $this->csml->answer($this->text, $this->session->get('min_hit_ratio_csml', $min_hit_ratio));

        // Нужно проверить. Если нет ответа по умолчанию, а остальные не подошли - попробуем разрулить ситуацию при помощи aiml
        if ($answer === false) {
            $aiml_answer = $this->doAiml();
            if ($aiml_answer !== false) {
                return $aiml_answer;
            }
        }
        // Получили подходящий ответ и работаем с ним
        $this->setVars($answer);
        // Обработаем условия перехода
        foreach ($answer->next as $next) {
            if ($this->checkCondition($next)) {
                $this->session->set('script_state', (string)$next);
                $block = $this->csml->block((string)$next);
                $this->setVars($block);
                return $this->answerFromCsmlBlock($block);
            }
        }
        throw new Exception("The block \"".$this->session->get('script_state', 'start')."\" does not contain a refference to next block."); 
    }
    
    private function checkCondition($next) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->log('$next='. print_r($next, true), 15);
        $if = (string)$next['if'];
        if ($if) {
            if ( isset($next['eq']) ) {
                return ($this->session->get($if, null) == (string)$next['eq']);
            } elseif ( isset($next['ne']) ) {
                return ($this->session->get($if, null) != (string)$next['ne']);
            } elseif ( isset($next['match']) ) {
                return (!!preg_match((string)$next['match'], $this->session->get($if, null)));
            } elseif ( isset($next['notmatch']) ) {
                return (!preg_match((string)$next['notmatch'], $this->session->get($if, null)));
            } else {
                return ($this->session->get($if, null) ? true : false);
            }
        } else {
            return true;
        }
    }


    private function doAiml($stupid=true) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->log('$stupid='. print_r($stupid, true), 15);
        $context = $this->session->get('context', '');
        $tags = $this->session->get('tags', '');
        if (!$tags) $tags = '#ever';
        $xml = $this->aiml->getAiml();
        $min_hit_ratio = isset($xml['minhit']) ? $xml['minhit'] : 80;
        $matches = $this->aiml->bestMatches($this->text, $tags, $this->session->get('min_hit_ratio_csml', $min_hit_ratio));
        if ( $context ) {
            $matches = array_merge(
                $matches,
                $this->aiml->bestMatches($this->text.' '.$context, $tags, $this->session->get('min_hit_ratio_csml', $min_hit_ratio)),    
                $this->aiml->bestMatches($context.' '.$this->text, $tags, $this->session->get('min_hit_ratio_csml', $min_hit_ratio))    
            );
        }
        krsort($matches);
        if (count($matches)) {
            $category = array_shift($matches)[1];
        } elseif ($stupid) { // Если работаем в режиме глупца - вернем ответ на все случаи жизни
            $matches = $this->aiml->bestMatches('Любая фигня', $tags.' #anyway');
            $category = array_shift($matches)[1];
        } else { // В режиме умного - промолчим - за умного сойдем
            return false;
        }
        return $this->answerFromAimlCategory($category);
    }
    
    private function answerFromCsmlBlock($block) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->log('$block='. print_r($block, true), 15);
        $hints = [];
        foreach ($block->hint as $hint) {
            $hints[] = $this->subst((string)$hint);
        }
        $commands = [];
        foreach ($block->command as $command) {
            $commands[] = $this->subst((string)$command);
        }
        $templates = [];
        foreach ($block->template as $template) {
            $templates[] = (string)$template;
        }
        $answer = [
            'text' => $this->subst($templates[rand(0, count($templates)-1)]),
            'hints' =>  $hints, 
            'commands' => $commands
        ];
        return $answer;
    }
    
    private function answerFromAimlCategory($category) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->log('$category='. print_r($category, true), 15);
        $this->setVars($category);
        
        if (!isset($category->template)) {
            return false;
        }
        foreach ($category->template as $template) {
            $templates[] = $template;
        }
        $rand = rand(0, count($templates)-1);
        $selected = $templates[$rand];
        $this->setVars($selected, false);

        $hints = [];
        if (isset($selected->hint)) {
            foreach ($selected->hint as $hint) {
                $hints[] = $this->subst((string)$hint);
            }
        }
        
        $commands = [];
        if (isset($category->command)) {
            foreach ($category->command as $command) {
                $commands[] = $this->subst((string)$command);
            }
        }
        if (isset($selected->command)) {
            foreach ($selected->command as $command) {
                $commands[] = $this->subst((string)$command);
            }
        }
        
        $answer = [
            'text' => $this->subst($selected->text),
            'hints' => $hints,
            'commands' => $commands
        ];
        return $answer;
    }


    private function setVars($parent_object, $reset=true) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->log('$parent_object='. print_r($parent_object, true), 15);
        if ($reset) {
            $this->session->set('context', '');
            $this->session->set('tags', '');
        }
        if (!isset($parent_object->var)) {
            return;
        }
        foreach ($parent_object->var as $var) {
            $value = (string)$var;
            if (($var['name'] == 'needhelp') && $this->session->get('proxy_to')) {
                continue;
            }
            $this->session->set((string)$var['name'], $this->subst($value, $parent_object));
            switch ($var['name']) {
                case 'script_file':
                    $this->csml->loadCsml($value);
                    break;
                case 'aiml_file':
                    $this->aiml->loadAiml($value);
                    break;
            }
        }
    }
    
    private function subst($text, $obj=null) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->log('$text='. print_r($text, true), 15);
        $this->log('$obj='. print_r($obj, true), 15);
        if ($obj && $obj->validated) {
            $validated = json_decode((string)$obj->validated, true);
        } else {
            $validated = [];
        }
        
        $n = new lhRuNames();
        
        $result = $text;
        $result = preg_replace("/__user_answer__/", $this->text, $result);
        
        if (preg_match("/__vocative__/", $result)) {
            try {
                $vocative = $n->shortVocative($this->session->get('name', '## UNDEF ##'));
            }
            catch (Exception $e) {
                $vocative = [$this->session->get('name', '## UNDEF ##')];
            }
            $result = preg_replace("/__vocative__/", $vocative[rand(0, count($vocative)-1)], $result);
        }
        
        if (preg_match("/__unformal__/", $result)) {
            try {
                $unformal = $n->unformal($this->session->get('name', '## UNDEF ##'));
            }
            catch (Exception $e) {
                $unformal = [$this->session->get('name', '## UNDEF ##')];
            }
            $result = preg_replace("/__unformal__/", $unformal[rand(0, count($unformal)-1)], $result);
        }
        
        $result = preg_replace_callback("/__validated_(\w+)__/", function ($matches) use($validated) {
            return isset($validated[$matches[1]]) ? $validated[$matches[1]] : '## UNDEF ##';
        }, $result);
        
        $result = preg_replace_callback("/__(\w+)__/", function ($matches) {
            return $this->session->get($matches[1], '## UNDEF ##');
        }, $result);
        
        $result = lhTextConv::genderSubstitutions($result, $this->session->get('gender', 1) ? 'm' : 'f');
 
        $result = lhTextConv::smilesSubstitutions($result);
        return $result;
    }
}
