<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of lhAbstractChatterBox
 *
 * @author user
 */
require_once __DIR__ . '/../interface/lhChatterBoxInterface.php';

abstract class lhAbstractChatterBox extends lhSelfTestingClass implements lhChatterBoxInterface {
    
    protected $session; // Экземпляр класса lhSessionFile для хранения данных сессии
    protected $aiml; // Экземпляр класса lhAIML для доступа к данным AIML
    protected $csml; // Экземпляр класса lhCSMS для доступа к скрипту CSML (Chat Script Markup Language)
    protected $text;  // Обрабатываемый текст (реплика пользователя)
    
    public function __construct($session, $aiml=null, $csml=null) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        if (!is_a($session, 'lhAbstractSession')) {
            throw new Exception("lhAbstractChatterBox::__construct needs an lhAbstractSession child instance as it's first argument");
        }
        $this->session = $session;
        
        if ($aiml) {
            if (!is_a($aiml, 'lhAbstractAIML')) {
                throw new Exception("lhAbstractChatterBox::__construct needs an lhAbstractAIML child instance or null as it's second argument");
            } else {
                $this->aiml = $aiml;
            }
        } else {
            $this->aiml = new lhAIML();
        }

        if ($csml) {
            if (!is_a($csml, 'lhAbstractCSML')) {
                throw new Exception("lhAbstractChatterBox::__construct needs an lhAbstractCSML instance or null as it's third argument");
            } else {
                $this->csml = $csml;
            }
        } else {
            $this->csml = new lhCSML();
        }
        
        $alt_csml = $this->session->get('script_file', false);
        if ($alt_csml) {
            $this->csml->loadCsml($alt_csml);
        }
        
        $alt_aiml = $this->session->get('aiml_file', false);
        if ($alt_aiml) {
            $this->aiml->loadAiml($alt_aiml);
        }
    }
    
    public function setAIProvider($lhaiml_instance) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->aiml = $lhaiml_instance;
    }

    public function setScriptProvider($lhcsml_instance) {
        $this->log(__CLASS__.'->'.__FUNCTION__);
        $this->csml = $lhcsml_instance;
    }
    
    public function session() {
        return $this->session;
    }

    public function aiml($param=null) {
        $result = $this->aiml;
        if ($param !== null) {
            $this->setAIProvider($param);
        }
        return $result;
    }
    
    public function csml($param=null) {
        $result = $this->csml;
        if ($param !== null) {
            $this->setScriptProvider($param);
        }
        return $result;
    }
}
