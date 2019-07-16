<?php 

class VindiExtensionCreditCardCest
{
    public function _before(AcceptanceTester $I)
    {
        // Caso a extensão já tenha sido configurada
        if (! $I->isModuleConfigured())
            $I->setConnectionConfig($I);
    }

    public function buyAnSimpleProduct(AcceptanceTester $I)
    {
        $I->addProductToCart($I);
        $I->fillCheckoutForm($I);
        $I->fillPaymentDetails($I);
        $I->click('#button-confirm');
        $I->waitForElement('#common-success', 30);
        $bill = $I->getLastVindiBill();
        if ($bill['amount'] != '106.0')
            throw new \RuntimeException;
    }
}
