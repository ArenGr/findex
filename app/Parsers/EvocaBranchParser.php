<?php

namespace App\Parsers;

/**
 * Evoca's branch listing, same theme and markup as Araratbank's.
 *
 * Most entries print only a bare time span ("09:30 - 17:00") with no day
 * beside it, so those branches store an address and a position but no
 * opening hours. A few - the mall branches - do name their days and parse
 * in full. Filling the rest in as Monday-Friday would be a guess, and this
 * app does not guess at opening hours.
 */
class EvocaBranchParser extends MapBoxBranchParser {}
