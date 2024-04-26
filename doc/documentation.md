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
Form submits made by the user, that change data in the database are serialized and stored in a user action table. 


# Admin
User can be edited in the Admin area. 
Password can not be changed.
No user can be created. 

# Prices
Prices are either unitary, subscription or voucher.
Only one unitary price can be active at once (UNIQUE CONSTRAINT)
This way the value of a unit is always clear.

Subscription system is not implemented yet.

# Booking
## Entities
### Business Day
Business Days are created by a cron job for 6 months in advance - unless the command is executed manually with another date. 
Weekends and public holidays are closed by default.
BusinessDays can be administered in the backoffice.

### Room
Rooms can be created in the backoffice. They have a capacity and are either open or closed. This allows to close a 
room for a longer period of time.
Rooms can also have workstations, they have no effect of the capacity of the room.

### Booking
Bookings contain the BusinessDay, the room and the user and the amount. The amount usually is the current unitary price. 
They can also contain the workstation.
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
For further details on the payment process, see the section Payment System. 


## Admin
### Business Day
BDs can only be opened or closed.

### Room
Rooms can be opened or closed and also created or deleted.

### Booking
Bookings can be created, edited and deleted. 


# Vouchers
Vouchers can be bought by the user.

## Entities
VoucherType : Defines how many units a voucher has, and how long it is valid.
Voucher: The actual voucher

## Process
Vouchers are attached to the User and the Invoice they were bought with. 
Vouchers are not transferable, they can only be used by the user they were bought for.
They also can only be used once they are paid for.
A voucher has the value of the unitary price at the moment of purchase.


# Payment System
A booking can be paid by voucher, Paypal or bank transfer.

A booking has an invoice.
It has one line, which corresponds to the booking.
The amount due is the booking price.

If it is paid by voucher, there is a second line and the voucher amount is substracted from the booking price. 
The standard use is that both lines cancel each other out an the invoice's total amount is 0. 
In case of a negative amount an E-Mail is sent to the admin. 
A voucher can also only partially pay for the booking, in which case the invoice has a total amount > 0.

If the booking is paid by paypal, the invoice amount will stay the booking price, but it will be mentioned that
the invoice has been paid by paypal.

If the booking is paid by bank transfer, the invoice amount will stay the booking price,
but it will contain a due date.

The goal is that the invoices mirror the money flux. 
In case of a voucher, there is no money exchange, as they vouchers have already been bought.


An invoice can have several payments.
A payment can be either a voucher or a transaction.

A transaction (actual money transfer) can be a bank transfer or a paypal.
A transaction can be split into several payments, in order to attach it to the correct invoice.



