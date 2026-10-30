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
Every price belongs to a booking type (Ganztag or Halbtag).
There is one active unitary price per booking type, so the value of a unit is always clear for a booking.
Voucher prices are the multi-unit packs of a booking type, e.g. ten half days.

Subscription system is not implemented yet.

# Booking
## Entities
### Business Day
Business Days are created by a cron job for 6 months in advance - unless the command is executed manually with another date. 
Weekends and public holidays are closed by default.
Public holidays (Rhineland-Palatinate) are resolved for the year of each date, so a run in autumn also closes
holidays of the following year. `PublicHolidayService::getGermanPublicHolidays()` defaults to the current year of the
Symfony clock.
BusinessDays can be administered in the backoffice.

### Room
Rooms can be created in the backoffice. They have a capacity and are either open or closed. This allows to close a 
room for a longer period of time.
Rooms can also have workstations, they have no effect of the capacity of the room.

### Booking Type
A booking type is either Ganztag (full day) or Halbtag (half day). Types can be activated and deactivated in the backoffice.
The type only decides which price and which vouchers apply. A half day occupies the room for the whole business day,
it counts like a full day for the capacity of the room. There is no morning or afternoon.

### Booking
Bookings contain the BusinessDay, the room, the user, the booking type and the amount. The amount usually is the current unitary price of the booking type. 
They can also contain the workstation.
If rooms or workstations are used, it will be configurable in the future.

### Workstation
The entity exists but is not used in the code yet. 

## Process
A step bar on top of the booking pages shows the three steps for the user: choose day, pay, confirmation.

### Step Day
The user selects a business day. At this point, no changes in the database are made.
The open business days of the next 14 days are listed as cards (`BookingController::DAY_LIST_LENGTH`), 
each showing how many places are already booked, so users can see whether someone else will be there.
Rooms without capacity are not counted.
If only one room can be booked, the card's booking type buttons directly submit the room step for this room,
so the booking is created in one click. With several rooms, the card leads to the room step.
Days further in the future can be chosen with the date field below the list. It starts empty, so that today can be chosen as well.

### Step Room
User selects a room. Which creates a booking.
With only one bookable room, there is no room selection: the page shows the date and the occupancy.
The booking type is chosen with the button that creates the booking: there is one button per active booking type with an active unitary price,
labelled with the type and its price ("Ganztag · 20 €", "Halbtag · 12 €"). The day cards of step date show the same buttons.
A submitted booking type that is inactive or has no active unitary price is rejected.
If the user does finalize the booking, it will be removed from the database after one day. 

### Step Payment
The price offered is the active unitary price of the booking type. A submitted price of another booking type is rejected.
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
Bookings can be created or canceled. The booking type is chosen when a booking is created.
A new booking automatically creates an invoice.

### Booking Type, Price and Voucher Type
Prices and voucher types are assigned to a booking type. Booking types can not be deleted.


# Vouchers
The user can buy vouchers.

## Entities
VoucherType: Defines how many units a voucher has, and how long it is valid at the value of the sigle voucher. It belongs to a booking type, except the refund voucher type.
Voucher: The actual voucher with its name that will be used for display and its price, and its voucherType

## Process
Vouchers are attached to the User and the Invoice they were bought with. 
Vouchers are not transferable, they can only be used by the user they were bought for.
They also can only be used once they are paid for.
A voucher has the value of its voucherType. 
A voucher can only pay for a booking of the same booking type: a Halbtag voucher does not pay for a Ganztag booking and the other way round.
Vouchers without a booking type, the refund vouchers, can pay for a booking of any type. A refund voucher is credit for what the user paid, whatever the booking type was.

### Invoice
The Invoice for the voucher contains the voucher codes. 

# Payment System
There are three payment methods: voucher, PayPal and bank transfer.
For voucher purchase, voucher is of course not applicable.
For bookings, invoice is only available when the booking could theoretically be canceled. This should avoid bookings to be paid by invoice at the last minute. 
The user can select among their valid (paid for, not expired, not used) vouchers to pay for a booking.

### Invoice numbers
Invoice numbers are the prefix `CO`, the current year and a running number with four digits, e.g. `CO20240001`.
The next number is the highest existing number starting with `CO` + current year, plus one. The invoice date and `createdAt` are not used: invoices can be backdated, and several invoices can share the same `createdAt` (e.g. imported data).
The highest number is sorted by length first, so `CO202410000` counts as higher than `CO20249999` once a year passes 9999 invoices.
Fixtures use the same format, numbered in date order without duplicates across fixtures that are loaded together.

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




# Widerruf
Consumers have a statutory right of withdrawal of 14 days for contracts concluded online (§ 312g BGB).
Since 19 June 2026 the electronic withdrawal function of § 356a BGB is mandatory ("Widerrufsbutton").
The analysis and the remaining steps are in `doc/widerrufsrecht-plan.md`.

## Widerrufsbelehrung
The Widerrufsbelehrung with the Muster-Widerrufsformular is the public page `/widerruf`. The text is in the
translations (`withdrawal.policy`) and follows the statutory model of Anlage 1 and 2 to Art. 246a EGBGB.
It is linked in the footer of every page, below the payment forms of bookings and vouchers, and in the
booking and voucher invoice e-mails.

## Withdrawal function
`/vertrag-widerrufen` is public, it must not require a login. The footer link "Vertrag widerrufen" is on every page.
1. The user enters their name, the e-mail address for the receipt, and the contract (invoice number). No reason is asked.
   Logged-in users get their name and e-mail prefilled and their invoices of the last 14 days as suggestions.
   The invoice e-mails link to the form with the invoice number prefilled (`?contract=`).
2. A summary page shows the declaration, and the button "Widerruf bestätigen" sends it.

On confirmation a `Withdrawal` is saved with the time of receipt, the user gets the Eingangsbestätigung by e-mail
(content of the declaration, date and time of receipt in Europe/Berlin) and the admin is notified.
If the invoice number exists and belongs to the logged-in user or to the entered e-mail address, the invoice and its user
are attached to the withdrawal. Nothing is cancelled or refunded automatically: the admin processes the withdrawal
in the backoffice (Widerrufe) and sets the status to processed or rejected.
A failed e-mail is logged, the withdrawal is still saved.
The confirmation is rate limited per IP (5 per 15 minutes, `config/packages/rate_limiter.yaml`), not in the test environment.

The withdrawal period is 14 days, but the admin decides about late withdrawals: a missing or wrong Widerrufsbelehrung
extends the period, so the form does not refuse any contract.

## Processing a withdrawal in the admin
In the backoffice (Widerrufe) the action "Widerruf bearbeiten" is shown for withdrawals with status received and an
attached invoice. Withdrawals without an invoice (unknown number or other e-mail) are handled manually: attach the
invoice in the edit form first, or set the status and comment by hand.
The action opens a page with the invoice, its payments, bookings or vouchers, a comment field and, for a booking of
today, the checkbox "the desk was not used". The logic is in `WithdrawalManager::processWithdrawal`.

Booking invoice:
- All not cancelled bookings of the invoice must be in the future (or today and marked as not used). Otherwise the
  withdrawal is rejected: the service has been fully performed and the right of withdrawal expired (§ 356 Abs. 4 BGB).
  This only holds if the user asked for the service to start before the end of the withdrawal period
  (step 2 of `doc/widerrufsrecht-plan.md`); the admin can overwrite the rejection text with the comment.
- If all bookings are already cancelled, the withdrawal is rejected.
- Otherwise the bookings are cancelled with `BookingManager::cancelBookingAfterWithdrawal`. It does not create a
  refund voucher and does not send the booking cancellation e-mail: a withdrawal must be refunded with the original
  payment method (§ 357 BGB), the user gets the withdrawal result e-mail instead.

Voucher invoice:
- If all vouchers have been used, the withdrawal is rejected (fully performed).
- Unused vouchers are invalidated (`VoucherManager::invalidateVoucher`, expiry date set to yesterday).
- Refund = invoice amount − value compensation (Wertersatz). The value compensation is pro rata on the price paid:
  invoice amount × used vouchers / all vouchers of the invoice, rounded down in favour of the user.
- Vouchers of an unpaid invoice can not have been used; if they were, the manager refuses and the admin decides.

Refund (`RefundManager::refundInvoiceAfterWithdrawal`):
- Unpaid invoice: cancelled with a reversal invoice (`refundInvoiceWithReversalInvoice`), nothing to pay back.
- Paid by voucher: the expiry date of the vouchers is reset (same as a normal cancellation).
- Paid by PayPal or bank transfer: `refundPaidInvoiceWithReversalInvoice` creates a reversal invoice of the refund
  amount with a refund `Payment` of the negative amount, so the reversal invoice is settled and the original invoice
  stays paid. **The money is not paid back automatically** (`PayPalService` has no refund call): the admin gets a
  warning with the amount and must refund it in the PayPal account or by bank transfer within 14 days of the receipt
  of the withdrawal.
- Partially paid invoices are refused, the admin handles them manually.

The withdrawal gets the status processed or rejected. The admin comment is the text entered on the processing page,
or a default text: the refund summary for processed withdrawals, the rejection reason for rejected ones.
The user gets an e-mail at the address of the withdrawal (`WithdrawalMailerService`): for processed withdrawals the
refund (reversal, vouchers valid again, amount and payment method, value compensation), for rejected ones the admin
comment as reason. A failed e-mail is logged, the status is still saved.

# Create Booking via Admin

Create booking via Admin creates the invoice entity to be paid. 
Consulting the PDF of the invoice creates it. 
Adding a voucher payment, regenerates the invoice with the voucher payment.

# Development
## Development data
`bin/console doctrine:fixtures:load --group=dev` loads `DevFixtures`, which only ties together the fixtures for a running app:
- the combinable test fixtures (`BasicFixtures`, `PriceFixtures`, `VoucherFixtures`, `InvoiceFixtures`, `BookingWithInvoiceNoPaymentFixture`),
- `HalfDayPriceFixtures`: the Halbtag prices next to the Ganztag prices of `PriceFixtures`, 12 € for a single half day and 100 € for ten.
- `UpcomingBusinessDayFixtures`: business days from today for the next 6 months. Weekends and public holidays are closed, like with the `app:business-days` command.
- `CoworkingRoomFixtures`: a room "Coworking-Raum" with 4 places, like the real space. The test rooms are closed, so that like in production only one room can be booked.
- `UpcomingBookingFixtures`: one booking on the second open day, the fourth open day is fully booked, so the occupancy on the booking page can be checked.
- `InvoiceBulkDownloadFixtures`: demo invoices over the last three months for the bulk download (see Demo invoices).

The tests don't use these fixtures, as they rely on fixed dates in 2024.

Loading all fixtures without a group fails, because the fixtures in `DataFixtures/Test` use the same references as the basic fixtures.
The booking fixtures extend `BookingFixtures` and load its bookings again, so only `BookingWithInvoiceNoPaymentFixture` is a dependency of `DevFixtures`.
All fixture users have the password `Passw0rd`. `admin@annabreyer.dev` is a super admin, `user.one@annabreyer.dev` a regular user.

## Tailwind
The project (`tailwind.config.js`, `@tailwind` directives in `app.css`) is set up for Tailwind v3.
The version is pinned in `config/packages/symfonycasts_tailwind.yaml`, because without it the bundle downloads the latest release (v4), which fails to build.

# Invoices via Admin

## Bulk download
Invoices can be downloaded as a ZIP archive from the invoice list in the Admin area. Only super admins can use this.

- **Selected invoices:** select invoices in the list and use the batch action "Rechnungen herunterladen (ZIP)". This only covers the invoices on the current page.
- **Filtered invoices:** "Alle gefilterten Rechnungen herunterladen (ZIP)" downloads every invoice matching the current search and filters, across all pages. For example, filter by date to export all invoices of one month.

Each PDF in the archive is named after its invoice number. If an invoice has no PDF yet, the PDF is generated before it is added to the archive. The archive is a temporary file and is deleted once it has been sent.

Batch actions on the invoice list are not confirmed, because the ZIP download is the only batch action there. If a destructive batch action is added to the invoice list, the confirmation has to be turned back on.

## Demo invoices
`InvoiceBulkDownloadFixtures` creates demo invoices for testing the bulk download: four per month over the last three months. It builds on `BasicFixtures` and `PriceFixtures`. The invoices go to the existing fixture users (`user1`, `user2`, `firstBookingUser`) and take their description and amount from the existing prices. Every third invoice is unpaid, and no PDFs are generated.

Loading the fixture also loads its dependencies, so the database is purged first:

```bash
php bin/console doctrine:fixtures:load --group=InvoiceBulkDownloadFixtures
```

The fixture is also part of the `dev` group (see Development data).

The fixture takes the first free invoice number once and counts up from there. `InvoiceManager::getInvoiceNumber()` only sees invoices that are already saved, and the fixture saves all its invoices at once.
