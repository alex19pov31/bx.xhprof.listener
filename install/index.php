<?php

IncludeModuleLangFile(__FILE__);

use Bitrix\Main\EventManager;
use \Bitrix\Main\ModuleManager;
use Bx\Xhprof\Listener\XHProfListener;

class bx_xhprof_listener extends CModule
{
    public $MODULE_ID = "bx.xhprof.listener";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $errors;

    public function __construct()
    {
        $this->MODULE_VERSION = "1.0.0";
        $this->MODULE_VERSION_DATE = "2025-02-07 12:07:05";
        $this->MODULE_NAME = "XHProf listener";
        $this->MODULE_DESCRIPTION = "Клиент с подпиской на события OnPageStart и OnAfterEpilog";
    }

    public function DoInstall(): bool
    {
        $this->registerEvents();
        ModuleManager::RegisterModule($this->MODULE_ID);
        return true;
    }

    public function DoUninstall(): bool
    {
        $this->unregisterEvents();
        ModuleManager::UnRegisterModule($this->MODULE_ID);
        return true;
    }

    private function registerEvents(): void
    {
        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            XHProfListener::class,
            'onStart'
        );
        $eventManager->registerEventHandler(
            'main',
            'OnAfterEpilog',
            $this->MODULE_ID,
            XHProfListener::class,
            'onEnd'
        );
    }

    private function unregisterEvents(): void
    {
        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            XHProfListener::class,
            'onStart'
        );
        $eventManager->unRegisterEventHandler(
            'main',
            'OnAfterEpilog',
            $this->MODULE_ID,
            XHProfListener::class,
            'onEnd'
        );
    }
}
