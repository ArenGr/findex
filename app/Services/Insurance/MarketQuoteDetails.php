<?php

namespace App\Services\Insurance;

use JsonSerializable;

/**
 * The extra details some insurers demand before they will quote at all.
 *
 * Sil Insurance's calculator will not return a premium without a phone
 * number, an email address and a **bank account number** - the last of which
 * is far more than a price comparison has any business asking for. So the
 * request form asks for these separately and optionally, under their own
 * explanation: fill them in and the comparison widens, leave them and the
 * quote runs against the insurers that need nothing beyond a plate and an ID
 * (see AutoInsuranceQuoteService).
 *
 * That opt-in is the whole point. Nobody is made to hand over a bank account
 * number to find out what their insurance costs, and anybody who does has
 * been told what it buys them.
 *
 * NOTE: the request this feeds is Sil's `action=contract` against draft.php,
 * which creates a *draft contract* on their side rather than performing a
 * passive price lookup - which is why the consent text says quotes are being
 * requested through Sil's platform rather than implying a read-only query.
 * Deliberate, and recorded here so it is not mistaken for an oversight.
 *
 * Like QuoteIdentity, none of this is ever stored: the bank account number in
 * particular exists only for the length of the outbound call.
 */
final class MarketQuoteDetails implements JsonSerializable
{
    use RedactsSensitiveValues;

    public function __construct(
        #[\SensitiveParameter]
        public readonly string $phone,
        #[\SensitiveParameter]
        public readonly string $email,
        #[\SensitiveParameter]
        public readonly string $bankAccountNumber,
    ) {}
}
