Coworking App

# Purpose
This app is designed to help small coworking spaces manage their members, bookings, and payments.
I have written it for my own coworking space. 

# Technical Choices
I have chosen to use the following technologies:
- Php 8
- MySQL 8
- Symfony 7

Originally I wanted to write an API, but as I am not very familiar with front end technologies, I have chosen to write a full stack app.
However, I kept in mind that it might be transformed into an API in the future.

## UUId
I have chosen to use UUIds in the URLs for sensitive entities.

## Forms
Most of the forms are simple HTML forms, without the Symfony Form component. But always with CSRF protection. 
This choice should allow me to be more flexible with the use of the forms, making ajax calls easier and/or transform the code into an API

## Code Quality Tools
PHPStan & PHPCsFixer

## Testing
I have written some tests for the most critical parts of the app.
They are a mix of unit and functional tests.
As it is a small app, testing fixtures a simple Doctrine Fixtures. 

## Configuration 
Specific parameters are found in the services.yaml file. I have chosen this way, as they are not environment dependent
and thus should not be found in the .env file. 
I did not want to do a setting table in the database, as I think it is overkill for this small app.
Depending on how this app might be used by others in the future, this might change.

## Translations & Internationalization
The App has two languages, German and English. Number and Date formats are hard coded to German standard.
Depending on the future use of the app, this might change.

## Admin
I have chosen EasyAdmin for the Admin interface. It is a simple and easy to use tool.


# Documentation
The documentation is in the doc folder.
Some functionalities are very specific to my needs, like the invoices being send to the document vault.
At some point this feature will need to change its design, so it can be optional. 