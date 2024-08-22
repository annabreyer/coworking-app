# User

# Front
## Registration
User is logged in after registration. 
User needs to confirm their email address, the link sent in the email is valid for 1h. 
The Link to confirm the email can be access from another device than the one the registration was made.
If the user does not confirm their email adress, nothing happens. There are no restrictions on the account.

## Login
Standard Login without remember-me function

## Password forgotten
Standard password forgotten process

## Account
The user can edit their profile data. 
The user can not change their password, this needs to be done by the password-reset function. 

# Back
Form submits made by the user, that change data in the database are serialized and stored in a user action table.
Bookings are not stored in the user action table, but payments are.


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
If rooms or workstations are used, it will be configurable in the future.

### Workstation
The entity exists but is not used in the code yet. 

## Process
### Step Day
The First user selects a business day.
At this point, no changes in the database are made. 

### Step Room
User selects a room. Which creates a booking.
If the user does finalize the booking, it will be removed from the database after one day. 

### Step Payment
User can choose to pay an invoice or pay immediately by voucher or PayPal. 
Invoice payment is only possible if we are still in the timeframe for a booking to be canceled.
The choice of the payment method validates the booking and creates an invoice.
It has one line, which corresponds to the booking.
For further details on the payment process, see the section Payment System.

## Admin
### Business Day
BDs can only be opened or closed.

### Room
Rooms can be opened or closed and also created or deleted.

### Booking
Bookings can be created or canceled. 
A new booking automatically creates an invoice.


# Vouchers
The user can buy vouchers.

## Entities
VoucherType: Defines how many units a voucher has, and how long it is valid at the value of the sigle voucher.
Voucher: The actual voucher with its name that will be used for display and its price, and its voucherType

## Process
Vouchers are attached to the User and the Invoice they were bought with. 
Vouchers are not transferable, they can only be used by the user they were bought for.
They also can only be used once they are paid for.
A voucher has the value of its voucherType. 

### Invoice
The Invoice for the voucher contains the voucher codes. 

# Payment System
There are three payment methods: voucher, PayPal and bank transfer.
For voucher purchase, voucher is of course not applicable.
For bookings, invoice is only available when the booking could theoretically be canceled. This should avoid bookings to be paid by invoice at the last minute. 
The user can select among their valid (paid for, not expired, not used) vouchers to pay for a booking.

### Payment by invoice
The amount due is the voucher or booking price. The invoice contains a mention when its due.

### Payment by voucher (bookings only)
If it is paid by voucher, there is a second line and the voucher amount is subtracted from the booking price. 
The standard use is that both lines cancel each other out and the invoice's total amount is 0. 
In case of a negative amount, an E-Mail is sent to the admin. 
A voucher can also only partially pay for the booking, in which case the invoice has a total amount > 0.

### Payment by PayPal
The PayPal payment process is centered around the invoice.
The user is redirected to PayPal, and after the payment is done, the invoice is marked as paid.




# Create Booking via Admin

Create booking via Admin creates the invoice entity to be paid. 
Consulting the PDF of the invoice creates it. 
Adding a voucher payment, regenerates the invoice with the voucher payment.



