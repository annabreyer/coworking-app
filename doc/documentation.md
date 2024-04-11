# User

# Front
## Registration
User is logged in after registration. 
User needs to confirm their email address, the link sent in the email is valid for 1h. 
The Link to confirm the email can be access from another device than the one the registration was made.

## Login
Standard Login without remember-me function

## Password forgotten
Standard password forgotten process

## Account
The user can edit their profile data. 
The user can not change their password, this needs to be done by the password-reset function. 

# Back
All POST/PUT/PATCH/DELETE requests are subscribed and are stored in the database.
Except for uri's that do not modify the database, like the first booking step.


# Admin
User can be edited in the Admin area. Password can not be changed.
No user can be created. 


# Booking
## Entities
### Business Day
Business Days are created by a cron job for 6 months in advance - unless the command is executed amnually with another date. 
Weekends and public holidays are closed by default.
BusinessDays can be administered in the backoffice.

### Room
Rooms can be created in the backoffice. They have a capacity and are either open or closed. This allows to close a 
room for a longer period of time.
Rooms can also have workstations, they have no effect of the capacity of the room.

### Booking
Bookings contain the Businessday, the room and the user. They can also contain the workstation. 
If rooms or workstations are used will be configurable in the future.

### Workstation
The entity exists but is not used in the code yet. 

## Process
### Step Day
First User selects a business day.
At this point no changes in the database are made. 

### Step Room
User selects a room. Which creates a booking. 

### Step Payment
User can choose to pay an invoice or pay immediately by voucher or paypal.

#### By invoice
Invoice ins generated, send my email to the user. 

#### By voucher/ Paypal
to be implemented



## Admin
### Business Day
BDs can only be opened or closed.

### Room
Rooms can be opened or closed and also created or deleted.

### Booking
Bookings can be created, edited and deleted. 


# Payment System

A booking can be paid by voucher, Paypal or bank transfer.
Each booking generates an invoice. 
Vouchers can be bought, invoice is issued. 

A booking has an invoice. 
An invoice can have several payments. 

A payment can be either a voucher or a transaction.

A transaction (actual money transfer) can be a bank transfer or a paypal.
A transaction can have several payments. 
Transactions mirror the bank account / paypal account. 

Incoming money is one transaction, and it can be split into several payments, in order to attach it to the correct invoice.
