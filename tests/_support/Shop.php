<?php

trait Shop
{
    public function addProductToCart($I)
    {
        $I->amOnPage('/index.php?route=product/product&product_id=40');
        $I->click('#button-cart');
        $I->amOnPage('/index.php?route=checkout/checkout');
    }

    public function fillCheckoutForm($I)
    {
        $I->wait(1);
        $I->registeredUser() ? $this->loginAsUser($I) : $this->createNewUser($I);
        $I->wait(1);
        $I->click('#button-shipping-address');
        $I->wait(1);
        $I->click('#button-shipping-method');
        $I->wait(1);
        $I->checkOption('input[name="agree"]');
        $I->click('#button-payment-method');
        $I->wait(1);
    }

    public function createNewUser($I)
    {
        $I->click('#button-account');
        $I->fillField('#input-payment-firstname', 'Vindi');
        $I->fillField('#input-payment-lastname', 'OpenCart');
        $I->fillField('#input-payment-email', 'comunidade@vindi.com.br');
        $I->fillField('#input-payment-telephone', '+551159047380');
        $I->fillField('#input-payment-password', 'password123');
        $I->fillField('#input-payment-confirm', 'password123');
        $I->fillField('#input-payment-address-1', 'R Sena Madureira, 163');
        $I->fillField('#input-payment-address-2', 'Vila Clementino');
        $I->fillField('#input-payment-city', 'São Paulo');
        $I->fillField('#input-payment-postcode', '04021-050');
        $I->selectOption('#input-payment-country', 'Brasil');
        $I->selectOption('#input-payment-zone', 'São Paulo');
        $I->checkOption('input[name="agree"]');
        $I->click('#button-register');
        putenv("REGISTERED_USER=true");
    }

    public function fillPaymentDetails($I)
    {
        $I->fillField('#card-holder', 'Vindi Opencart');
        $I->selectOption('#card-flag', 'mastercard');
        $I->fillField('#card-number', '5555555555555557');
        $I->fillField('#card-expiry-month', '06');
        $I->fillField('#card-expiry-year', strval(date('y') + 5));
        $I->fillField('#card-cvc', '123');
    }

    public function loginAsUser($I)
    {
        $I->fillField('#input-email', 'comunidade@vindi.com.br');
        $I->fillField('#input-password', 'password123');
        $I->click('#button-login');
        $I->wait(1);
        $I->click('#button-payment-address');
    }
}
