<?php 

class VindiExtensionConfigCest
{
    public function setConnectionConfig(AcceptanceTester $I)
    {
        // Caso a extensão já tenha sido configurada
        if ($I->isModuleConfigured())
            return;

        $I->goToAdminPanel($I);
        $I->goToVindiSettings($I);
        $I->setConnectionConfig($I);
        $I->dontSeeElement('.alert-danger');
    }

    public function enableLogger(AcceptanceTester $I)
    {
        $I->goToAdminPanel($I);
        $I->goToVindiSettings($I);
        $I->selectOption('#select-debug-log', 'Habilitado');
        $I->click('.btn-primary');
        $I->dontSeeElement('.alert-danger');
    }
}

