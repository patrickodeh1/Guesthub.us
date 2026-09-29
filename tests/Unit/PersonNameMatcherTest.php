<?php

namespace Tests\Unit;

use App\Services\PersonNameMatcher;
use Tests\TestCase;

class PersonNameMatcherTest extends TestCase
{
    public function test_names_that_should_match(): void
    {
        $pairs = [
            ['JOHN DOE', 'John Doe'],
            ['JOHN  DOE', 'john doe'],
            ['JOHN MICHAEL DOE', 'John Doe'],           // middle name on the ID only
            ['JOHN DOE', 'John Michael Doe'],           // middle name typed only
            ['JOSÉ GARCIA', 'Jose Garcia'],             // accents
            ["MARY O'BRIEN", 'Mary OBrien'],            // apostrophe
            ['MARY O’BRIEN', "Mary O'Brien"],           // typographic apostrophe
            ['ANNA SMITH-JONES', 'Anna Smith Jones'],   // hyphen
            ['JOHN DOE JR', 'John Doe'],                // suffix on ID
            ['JOHN DOE', 'John Doe Jr.'],               // suffix typed
            ['DOE JOHN', 'John Doe'],                   // order swapped
            ['MARIA DE LA CRUZ', 'Maria Cruz'],         // compound surname, typed subset
        ];

        foreach ($pairs as [$id, $typed]) {
            $this->assertTrue(PersonNameMatcher::matches($id, $typed), "expected match: '{$id}' vs '{$typed}'");
        }
    }

    public function test_names_that_should_not_match(): void
    {
        $pairs = [
            ['JOHN DOE', 'John Smith'],                 // different last name
            ['JOHN DOE', 'Jane Doe'],                   // different first name
            ['JOHN', 'John Doe'],                       // one word only
            ['JOHN DOE', 'John'],
            ['', 'John Doe'],
            ['JOHN DOE', ''],
            [null, 'John Doe'],
            ['JOHN MICHAEL DOE', 'Michael Smith'],
        ];

        foreach ($pairs as [$id, $typed]) {
            $this->assertFalse(PersonNameMatcher::matches($id, $typed), "expected mismatch: '{$id}' vs '{$typed}'");
        }
    }

    public function test_normalize(): void
    {
        $this->assertSame('jose garcia', PersonNameMatcher::normalize('  JOSÉ   García '));
        $this->assertSame('', PersonNameMatcher::normalize(null));
    }
}
