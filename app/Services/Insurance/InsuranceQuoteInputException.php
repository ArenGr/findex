<?php

namespace App\Services\Insurance;

use RuntimeException;

/**
 * The insurer rejected the identifiers themselves - a plate that is not
 * registered, or an ID number that is not the registered owner's.
 *
 * Kept distinct from "this insurer failed to answer" because the two need
 * opposite handling. A failure is one partner's problem and the other quotes
 * still stand; bad identifiers are the *user's* problem and every insurer
 * will reject them identically, since they all read the same Motor Insurers'
 * Bureau registry. Showing seven declined cards would be a confusing way of
 * saying "you typed the wrong ID number", so this is surfaced back on the
 * form instead.
 *
 * The message comes from the insurer and is shown to the user, so providers
 * must pass through only the API's own validation text - never a raw
 * exception message, which would carry the request URL and with it the ID
 * number (see QuoteIdentity).
 */
class InsuranceQuoteInputException extends RuntimeException {}
