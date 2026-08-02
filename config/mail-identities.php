<?php

// Selectable "From" identities for admin-authored emails (see the
// Organizations "Message" action) - kept separate from mail.from (which
// stays the no-reply address every automated/system email uses) so an
// admin can pick which identity a one-off message reads as.

return [
    'findex-team' => [
        'label' => 'The Findex Team',
        'address' => 'findex-team@findex.am',
        'name' => 'The Findex Team',
    ],
    'support' => [
        'label' => 'Findex Support',
        'address' => 'support@findex.am',
        'name' => 'Findex Support',
    ],
    'agrigoryan' => [
        'label' => 'Aren Grigoryan (personal)',
        'address' => 'agrigoryan@findex.am',
        'name' => 'Aren Grigoryan, Findex',
    ],
];
