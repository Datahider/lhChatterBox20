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

abstract class lhAbstractChatterBox implements lhChatterBoxInterface {
    
    protected $session; // Экземпляр класса lhSessionFile для хранения данных сессии
    protected $aiml; // Экземпляр класса lhAIML для доступа к данным AIML
    protected $csml; // Экземпляр класса lhCSMS для доступа к скрипту CSML (Chat Script Markup Language)
    protected $text;  // Обрабатываемый текст (реплика пользователя)
    
    public function __construct($session, $aiml=null, $csml=null) {
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
    }
    
    public function setAIProvider($lhaiml_instance) {
        $this->aiml = $lhaiml_instance;
    }

    public function setScriptProvider($lhcsml_instance) {
        $this->csml = $lhcsml_instance;
    }

    
}
