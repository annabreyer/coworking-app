# Widerrufsrecht & Widerrufsbutton (§ 356a BGB) – Analysis and Implementation Plan

Status: steps 1 and 3 implemented on `feature/widerrufsbutton` (2026-10-30), steps 2 and 4 open. Not legal advice – the two open legal questions below should be checked by a lawyer.

## 1. The law

Since **19 June 2026** (BGBl. 2026 I Nr. 28, implementing Directive (EU) 2023/2673, no transition period),
§ 356a BGB requires every business that lets **consumers** conclude distance contracts via a website or app to offer
an electronic withdrawal function:

1. A clearly labelled function ("Vertrag widerrufen"), easy to find, available for the whole withdrawal period.
   Commentators read this as: reachable from every page (footer), not hidden behind a login.
2. The consumer enters or confirms only: **name**, **identification of the contract** (or part of it), and the
   **electronic channel** for the receipt (e-mail). No reason may be asked.
3. A second, clearly labelled confirmation step ("Widerruf bestätigen").
4. The trader sends an **Eingangsbestätigung** on a durable medium (e-mail) without delay, with the content of the
   declaration and **date and time of receipt**.
5. A declaration sent via the function within the period counts as received in time.

If the function is missing, the withdrawal period can extend to 12 months and 14 days, plus risk of Abmahnungen.
It applies only where a statutory Widerrufsrecht exists – which brings us to the coworking.

## 2. How it applies to the coworking

### Who are the customers?
Freelancers booking for their business are Unternehmer (§ 14 BGB) and have no Widerrufsrecht. But registration is
open to anyone, there is no company/B2B field, and remote employees or private users booking for themselves are
consumers (§ 13 BGB). **So the app concludes consumer contracts and § 356a applies**, unless the offer is turned into
a strict B2B-only offer (clear statement + checkbox + plausibility checks) – not realistic for a village coworking.

### Day bookings (Ganztag / Halbtag)
- Possible exemption § 312g Abs. 2 Nr. 9 BGB (services with a specific date in accommodation / leisure). Some
  coworkings rely on it, but **coworking is mostly work, not "Freizeitbetätigung"** – courts have not decided it and
  KG Berlin reads the exemption narrowly. Open legal question #1.
- Safer route: treat bookings as withdrawable services and use § 356 Abs. 4 / § 357a BGB: if the consumer expressly
  asks for the service to start before the 14 days end, the right **expires once the day has been used**, and a
  withdrawal before that owes value compensation only pro rata. Without that express request, a consumer could
  withdraw even after using the desk and owe nothing. Almost all bookings are within 14 days (the day list shows
  the next 14 days), so this matters.
- The existing user cancellation (`BookingController::cancelBooking`, up to `time_limit_cancel_booking_days` = 1 day
  before) is a contractual right, not the statutory withdrawal. It can stay, but does not replace the button.

### Vouchers (10er-Karten etc.)
Clearly withdrawable for 14 days after purchase (no date-specific exemption). If some units were used, the consumer
owes value compensation for those; the rest must be refunded **with the original payment method** (§ 357 Abs. 3)
– i.e. money back, not a refund voucher (`RefundManager::createRefundVoucher` is not enough for this case).

### Gaps found in the app today
- No Widerrufsbelehrung, no Muster-Widerrufsformular anywhere (templates, translations, e-mails, invoice PDFs).
  This was already required before June 2026 (Art. 246a EGBGB).
- No withdrawal function, footer only links to Impressum / Datenschutz.
- PayPal integration only captures payments, it cannot refund (`PayPalService` has no refund call).

## 3. Decisions needed before implementing

1. Rely on the § 312g Abs. 2 Nr. 9 exemption for day bookings, or go the § 356 Abs. 4 route? (Recommendation:
   § 356 Abs. 4 – it works either way, and the plan below assumes it.)
2. Refunds after withdrawal: automatic (PayPal refund API) or manual by the admin? (Recommendation: manual first,
   the volume is tiny; the app records and tracks the withdrawal.)
3. Have the Widerrufsbelehrung text checked by a lawyer / generator (e.g. IT-Recht Kanzlei, Händlerbund).

## 4. Implementation plan

### Step 1 – Information duties (no new logic)
- Widerrufsbelehrung + Muster-Widerrufsformular page (`TermsController`, route e.g. `/widerruf`), text in
  translations.
- Footer link in `templates/base.html.twig` to the Belehrung and to the withdrawal function (step 3).
- Link the Belehrung on the payment steps (`templates/booking/payment.html.twig`, `templates/voucher/index.html.twig`)
  and include it (or a link + text) in the booking confirmation and invoice e-mails (`BookingMailerService`,
  `InvoiceMailerService`).

### Step 2 – Express request to start early (bookings only)
- Checkbox on the booking payment step, required when the business day is within 14 days of the booking:
  "Ich verlange ausdrücklich, dass mit der Leistung vor Ablauf der Widerrufsfrist begonnen wird. Mir ist bekannt,
  dass mein Widerrufsrecht mit vollständiger Vertragserfüllung erlischt."
- Store it on `Booking` (e.g. `earlyPerformanceRequestedAt` datetime) + migration; validate in
  `BookingPaymentController::bookingStepThree` and `payWithVoucher`; reject the payment step without it.
- Repeat the consent in the booking confirmation e-mail (durable medium, required by § 356 Abs. 4).
- Admin-created bookings (`BookingCrudController`) are not distance contracts concluded online – no checkbox.

### Step 3 – Withdrawal function (§ 356a)
- New entity `Withdrawal`: name, e-mail, contract reference (invoice number), optional linked `Invoice`/`User`,
  `receivedAt` (immutable datetime), status (received / processed / rejected), admin comment. Migration.
- Public route `/vertrag-widerrufen` (no login required), linked from the footer on every page:
  - Page 1: form with name, e-mail, invoice/booking number. Prefilled when logged in, and logged-in users can pick
    the contract from a list of their invoices that are still within 14 days. No "reason" field.
  - Page 2: summary of what is being withdrawn + button **"Widerruf bestätigen"**.
  - Button label on the entry point: **"Vertrag widerrufen"**.
- On confirm: save `Withdrawal` with `receivedAt = now()`, send the Eingangsbestätigung e-mail (content of the
  declaration + date/time of receipt) to the entered address, and notify the admin (`AdminMailerService`).
- Do **not** auto-cancel from unauthenticated input; try to match the invoice number + e-mail to an `Invoice` and
  show it to the admin. Also record it as a `UserAction` when logged in, like other user form submits.
- Optionally show a "Vertrag widerrufen" button per invoice on `templates/user/invoices.html.twig` while the period
  is open, pointing to the same flow prefilled.
- Rate limit + CSRF on the public form (Symfony RateLimiter).

### Step 4 – Processing in the admin
- EasyAdmin CRUD for `Withdrawal` (list, filter by status, link to invoice).
- Action "Widerruf bearbeiten":
  - Booking not yet used → reuse `BookingManager::cancelBooking` (refund logic already exists).
  - Booking already used with early-start consent → reject with comment (right expired).
  - Voucher invoice → mark unused vouchers of the invoice as invalid, compute refund = price − value of used units,
    record a refund `Payment` / reversal invoice via `RefundManager` / `InvoiceManager::createReversalInvoice`,
    and pay back via PayPal or bank transfer manually.
- E-mail to the user when processed.

### Step 5 – Tests and docs
- Functional tests: public form without login, two-step confirmation, receipt e-mail content (date/time), checkbox
  required on payment step for near dates, admin processing for booking and voucher cases.
- Describe the process in `doc/documentation.md` (new section "Widerruf").

Effort estimate: steps 1–2 small (≈ 1 day), step 3 medium (≈ 1–2 days), step 4 medium (≈ 1 day), tests included.
Steps 1 and 3 are the legally urgent ones since the obligation is already in force.

## Sources
- Noerr: https://www.noerr.com/de/insights/umsetzungsgesetz-zum-widerrufsbutton-veroeffentlicht
- § 356a BGB: https://www.gesetze-im-internet.de/bgb/__356a.html
- id-law: https://www.id-law.de/blog/117/widerrufsbutton-nach--356a-bgb-diese-pflicht-gilt-seit-dem-19-juni-2026
- Verbraucherzentrale: https://www.verbraucherzentrale.de/wissen/vertraege-reklamation/kundenrechte/widerrufsbutton-ab-juni-2026-onlinevertraege-einfacher-widerrufen-118449
- § 312g exceptions: https://www.jurahilfe.de/blog/widerrufsrecht-312g-bgb
- Example coworking relying on Nr. 9: https://www.coworking-cassel.de/widerrufsbelehrung
