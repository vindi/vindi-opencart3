<?php

trait Settings
{
    public function goToAdminPanel($I)
    {
        $I->amOnPage('/admin');

        try {
            $I->fillField('#input-username', 'admin');
            $I->fillField('#input-password', 'admin123');
            $I->click('Acessar');
        } catch (Exception $e) { }
    }

    public function goToVindiSettings($I)
    {
        $I->click('#menu-extension');
        $I->wait(1);
        $I->click('Extensões', '#collapse2');
        $I->selectOption('.form-control', 'Pagamentos (1)');
        try { $I->click('.btn-success'); } catch (Exception $e) { }
        $I->click('.fa-pencil');
    }

    public function setConnectionConfig($I)
    {
        $I->selectOption('#select-status', 'Habilitado');
        $I->fillField('#input-api-key', getenv('VINDI_API_KEY'));
        $I->selectOption('#select-gateway', 'Sandbox (Test)');
        $I->click('.btn-primary');
        putenv("CONFIGURED=true");
    }
}
