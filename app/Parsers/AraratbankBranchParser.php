<?php

namespace App\Parsers;

/**
 * Araratbank's branch listing. The markup is read by MapBoxBranchParser;
 * this bank names its days in the hours line, so they parse in full.
 */
class AraratbankBranchParser extends MapBoxBranchParser {}
