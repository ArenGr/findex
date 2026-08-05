<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $writer = Writer::firstOrCreate(
            ['slug' => 'findex-editorial'],
            [
                'name' => 'Findex Editorial',
                'expertise' => 'Personal finance, banking, and consumer credit in Armenia',
                'topics' => 'credit cards, mortgages, currency exchange, insurance',
                'is_active' => true,
            ]
        );

        $writerUser = User::firstOrNew(['email' => 'writer@findex.test']);
        $writerUser->forceFill([
            'name' => 'Findex Editorial',
            'password' => Hash::make('password'),
            'role' => UserRole::WRITER,
            'writer_id' => $writer->id,
            'email_verified_at' => now(),
        ])->save();

        // The admin who "reviewed" the approved/rejected demo articles -
        // reuses AdminSeeder's account rather than minting a new one.
        $admin = User::firstOrNew(['email' => 'admin@findex.test']);
        if (! $admin->exists) {
            $admin->forceFill([
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
            ])->save();
        }

        $articles = [
            [
                'slug' => 'choosing-the-right-credit-card-in-armenia',
                'title' => 'Choosing the Right Credit Card in Armenia',
                'excerpt' => "Cashback, installment periods, annual fees - here's what actually matters when comparing credit cards from Armenian banks.",
                'body' => "Every bank in Armenia offers a credit card, and every bank's marketing page makes theirs sound like the obvious choice. In practice, the right card depends on how you actually spend, not which one has the flashiest ad.\n\nStart with the interest-free period. Most cards offer somewhere between 45 and 60 days if you pay the full balance before the due date. If you routinely carry a balance month to month, that grace period is irrelevant to you - what matters instead is the ongoing interest rate, which can vary by several percentage points between issuers.\n\nCashback and points programs look appealing on paper, but read the fine print on category caps and redemption rules. A 5% cashback card that caps rewards at 10,000 AMD per month is worth less than a flat 1.5% card with no cap if your spending is high.\n\nAnnual fees are often waived for the first year, then quietly kick in from year two. Check whether the fee is waived permanently above a minimum annual spend - many Armenian banks offer this, but you have to opt in or hit the threshold to keep it waived.\n\nFinally, look at foreign currency conversion fees if you travel or shop internationally. This is where cards differ the most, and it's the fee people notice least until they see their statement.",
            ],
            [
                'slug' => 'understanding-mortgage-rates-first-time-buyers-guide',
                'title' => "Understanding Mortgage Rates: A First-Time Buyer's Guide",
                'excerpt' => 'Fixed vs. floating, down payment requirements, and the government subsidy programs that can shave points off your rate.',
                'body' => "Buying your first home in Armenia means navigating a mortgage market where rates, terms, and eligibility for subsidy programs can differ meaningfully from bank to bank.\n\nThe first decision is fixed versus floating. A fixed rate locks in your monthly payment for the life of the loan (or a long initial period), which is easier to budget around but usually starts a bit higher than a floating rate. Floating rates track a reference rate and can rise or fall - fine if you expect rates to drop, riskier if you're stretching your budget already.\n\nDown payment requirements typically range from 10% to 30% of the property value depending on the bank, the property type, and whether you qualify for a state-subsidized program. Programs aimed at young families or first-time buyers can meaningfully lower both the down payment and the effective interest rate.\n\nDon't just compare the headline rate - compare the APR, which folds in origination fees, mandatory insurance, and other costs the bank doesn't put in the ad. Two loans with the same rate can have very different total costs once fees are included.\n\nGet pre-approved before you start seriously looking at properties. It tells you your real budget, and it makes your offer more credible to sellers once you find the right place.",
            ],
            [
                'slug' => 'five-ways-to-get-a-better-currency-exchange-rate',
                'title' => '5 Ways to Get a Better Currency Exchange Rate',
                'excerpt' => 'Small habits that add up: timing, bank choice, and the one common mistake that quietly costs you money on every exchange.',
                'body' => "Exchange rates move throughout the day and vary more between banks than most people realize. A little attention to timing and where you exchange can meaningfully change how much you walk away with.\n\nFirst, always compare buy and sell rates across at least three banks before a large exchange - the spread between the best and worst rate in the market is often wider than people expect, especially for less common currencies.\n\nSecond, avoid airport and hotel exchange counters entirely if you can. Their rates are built around convenience, not competitiveness, and the difference compared to a bank branch can be substantial.\n\nThird, larger amounts sometimes qualify for a better rate - it can be worth asking a branch directly if you're exchanging a significant sum, since posted rates aren't always the final word.\n\nFourth, avoid exchanging on weekends or holidays when spreads tend to widen due to lower liquidity in the market.\n\nFinally, track rates for a few days before a planned exchange rather than reacting to a single day's number - currency rates are noisy day to day, and a short delay can meaningfully improve your outcome.",
            ],
            [
                'slug' => 'understanding-osago-auto-insurance-in-armenia',
                'title' => "What Armenia's OSAGO Auto Insurance Actually Covers",
                'excerpt' => "It's mandatory for every registered car, but most drivers only learn what OSAGO really pays for after an accident.",
                'body' => "OSAGO (compulsory motor third-party liability insurance) is mandatory for every registered vehicle in Armenia, but a lot of drivers only find out what it actually covers after an accident.\n\nOSAGO pays for damage you cause to other people's vehicles, property, or health in an accident you're responsible for - not damage to your own car. If you want your own vehicle covered too, that requires a separate voluntary CASCO policy on top of OSAGO.\n\nThe payout is capped by law, not by your policy - every insurer offers the same statutory minimum coverage limits for OSAGO, since the terms are set by the state rather than by individual companies. This is why OSAGO pricing differs between insurers mainly on service and claims handling, not on coverage.\n\nPremiums are affected by your driving history, the age of the vehicle, and sometimes the driver's age and experience - a clean multi-year record with no at-fault claims typically brings the rate down significantly through Armenia's bonus-malus system.\n\nIf you're shopping around, compare how each insurer handles claims processing time and whether they have a direct settlement agreement with other insurers, not just the sticker price - a slightly higher premium is often worth it if it means a faster payout after an actual accident.",
            ],
        ];

        foreach ($articles as $data) {
            Article::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'writer_id' => $writer->id,
                    'title' => $data['title'],
                    'language' => 'en',
                    'excerpt' => $data['excerpt'],
                    'body' => $data['body'],
                    'featured_image' => "articles/{$data['slug']}.jpg",
                    'status' => Article::STATUS_APPROVED,
                    'reviewed_by' => $admin->id,
                    'published_at' => now()->subDays(random_int(1, 14)),
                ]
            );
        }

        Article::updateOrCreate(
            ['slug' => 'is-now-a-good-time-to-refinance-your-mortgage'],
            [
                'writer_id' => $writer->id,
                'title' => 'Is Now a Good Time to Refinance Your Mortgage?',
                'language' => 'en',
                'excerpt' => 'A look at when switching lenders actually pays off once you account for closing costs and remaining loan term.',
                'body' => "Refinancing can lower your monthly payment, but the math only works out if the savings outweigh the closing costs and the time left on your loan.\n\nAs a rule of thumb, refinancing tends to make sense when the new rate is at least one to one-and-a-half percentage points below your current rate, and you plan to stay in the property long enough to recoup the closing costs through lower payments.\n\nThis article is awaiting admin review before publishing.",
                'featured_image' => 'articles/is-now-a-good-time-to-refinance-your-mortgage.jpg',
                'status' => Article::STATUS_SUBMITTED,
            ]
        );

        Article::updateOrCreate(
            ['slug' => 'top-10-banks-in-armenia'],
            [
                'writer_id' => $writer->id,
                'title' => 'Top 10 Banks in Armenia',
                'language' => 'en',
                'excerpt' => 'A ranked look at the largest banks operating in Armenia today.',
                'body' => "This article ranks the ten largest banks operating in Armenia by assets and customer base.\n\nThe ranking below is based on publicly available figures as of last year.",
                'featured_image' => 'articles/top-10-banks-in-armenia.jpg',
                'status' => Article::STATUS_REJECTED,
                'rejection_reason' => 'The ranking needs sourced figures and a stated methodology before this can go live - right now the claims aren\'t backed by anything readers can verify.',
                'reviewed_by' => $admin->id,
            ]
        );

        Article::updateOrCreate(
            ['slug' => 'travel-insurance-101'],
            [
                'writer_id' => $writer->id,
                'title' => 'Travel Insurance 101',
                'language' => 'en',
                'body' => "Still drafting this one - notes on what to cover: medical coverage abroad, trip cancellation, baggage loss, and how Findex's own auto insurance quote flow compares to travel-specific policies.",
                'featured_image' => 'articles/travel-insurance-101.jpg',
                'status' => Article::STATUS_DRAFT,
            ]
        );

        $this->command->info('Articles seeded successfully! Writer login: writer@findex.test / password');
    }
}
