
# Le README  
 
- indications pour lancer le projet 
- peut etre des diagrammes 
- j'aime beaucoup ton readme :) 

# La documentation

- typo : "unless the command is executed amnually with another date" --> mannually 


# Les fichiers Commands
<details><summary>BusinessDaysCommand </summary>

## code 
L - 18
je mettrais une description :   --> description: 'Add a short description for your command',

J'ai eu du mal à comprendre ce qui était attendu dans ce fichier, mais il me manquait du contexte métier, 
les tests sont clairs et m'ont aidés à mieux comprendre! ( et la doc aussi qu eje n'avais pas lue hahaha) 

## tests 
remarque:
-> que se passe -til si tu met une date valide ET dans le passé ? Du context métier j'ai cru comprendre que ça n'avais pas de sens, mais je testerais bien ce cas pour l'empecher d'arriver par erreur. 

</details>



# Les fichiers Controllers

<details><summary>VoucherController </summary>

## code 

les erreurs ne sont pas fomalisées de la même manière.

ex: 
 'Invalid payment method selected.' vs 'Please select a payment method.'  j'aurais mis 'Payment method missing.' 
 de même pour 'Please select a voucher.' j'aurais mis 'Voucher missing.'

C'est un projet pour toi et il n'a pas vocation à trop se conplexifier, mais j'aime bien garder la même trame pour les message d'erreurs on s'y retrouve plus facilement quand cest p asnotre code ou si c'est du code spagetthi. 

## tests 

### create, generate & send by mail 
 
j'aurais fusionné `testFormSubmitWithPaymentMethodInvoiceCreatesVouchers` & `testFormSubmitWithPaymentMethodInvoiceGeneratesInvoice` & `testFormSubmitWithPaymentMethodInvoiceSendsInvoiceByMail`

car tu y teste 3 actions "createsInvoice", "GeneratesInvoice" & "SendInvoicesByMail" et toutes les 3 sont dans la même branche du if, elles me semblent sont indissociables. 

J'aurais peut être fait: 

- test 1 : cas passant on crée, on génére et on envoie l'email 
- test 2 : on crée  on génére mais l'envoie de mail fail: on vérifie qu'on catch bien l'erreur 
- test 3 : on crée, mais la génération du pdf fail: dans ce cas que fait-on, on annule l'envoie de mail on le garde? 
- test 4: la creation fail, que fait-on derrière? un log, un message utilisateur? 


### code non couvert? 

Code non couvert par les test ( en tout cas pas par les titres des tests :) ) : 

```php
$user = $this->getUser();
if (false === $user instanceof User) {
    throw new \LogicException('User is not a User Object?!');
}
```  
 -> je n'ai pas vu tester ce throw là, mais bon est-ce utile? par contre au moins tester fonctionnellement pour vérifier l'expérience utilisateur. 

```php
return $this->redirectToRoute('voucher_payment_paypal', ['voucherPriceId' => $voucherPriceId]);  
```  

-> c'est la toute dernière ligne, je n'ai pas vu de test sur ce rediercte là :) 


</details>

 