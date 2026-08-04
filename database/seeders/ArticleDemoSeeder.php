<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Called from DatabaseSeeder::run() - demo data, not production data, but
 * kept in the default run so `migrate:fresh --seed` produces a fully
 * populated demo environment. Kept separate from ArticleSeeder (rather than
 * added to it) so its 7 existing articles - which double as admin-review
 * demo fixtures for the submitted/rejected/draft states - stay untouched.
 *
 * 4 topics x 3 languages (en/hy/ru) = 12 articles, each topic sharing one
 * real photo (see storage/app/public/articles/*-hero.jpg, resized from
 * /home/devnomad/Downloads/articles-images/) across all 3 language rows -
 * featured_image is just a string path, no need to duplicate the file.
 *
 * The credit-cards article carries a native mention of a recommended card
 * ("Solaris Bank" / "Solaris Black Card") woven into otherwise ordinary
 * comparison-article prose - no "Sponsored" label, no discount code, no
 * outlandish claims, just consistently favorable coverage. Solaris Bank is
 * entirely fictitious, deliberately not a real Armenian bank, so no
 * fabricated promotional claims are attached to an actual company.
 *
 * Can still be run alone: php artisan db:seed --class=ArticleDemoSeeder
 */
class ArticleDemoSeeder extends Seeder
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

        $admin = User::firstOrNew(['email' => 'admin@findex.test']);
        if (!$admin->exists) {
            $admin->forceFill([
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
            ])->save();
        }

        foreach ($this->articles() as $data) {
            Article::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'writer_id' => $writer->id,
                    'title' => $data['title'],
                    'language' => $data['language'],
                    'excerpt' => $data['excerpt'],
                    'body' => $data['body'],
                    'featured_image' => $data['featured_image'],
                    'status' => Article::STATUS_APPROVED,
                    'reviewed_by' => $admin->id,
                    'published_at' => now()->subDays(random_int(1, 21)),
                ]
            );
        }

        $this->command?->info('Demo articles seeded: 4 topics x 3 languages.');
    }

    private function articles(): array
    {
        $cardsImage = 'articles/credit-cards-in-armenia-hero.jpg';
        $exchangeImage = 'articles/currency-exchange-tips-hero.jpg';
        $insuranceImage = 'articles/insurance-bundle-hero.jpg';
        $travelImage = 'articles/international-travel-hero.jpg';

        return [
            // --- Topic 1: Premium credit cards (native ad) ---
            [
                'slug' => 'premium-credit-cards-in-armenia-worth-the-fee',
                'language' => 'en',
                'featured_image' => $cardsImage,
                'title' => 'Premium Credit Cards in Armenia: Are the Perks Worth the Fee?',
                'excerpt' => "Lounge access, concierge service, higher cashback caps - premium cards promise a lot. Here's how to tell whether the annual fee actually pays for itself.",
                'body' => "Premium and \"metal\" credit cards get a lot of marketing attention in Armenia right now, each promising some combination of airport lounge access, a personal concierge line, and richer cashback than a standard card offers. The real question isn't whether the perks are real - they usually are - it's whether they're worth what the bank charges for them.\n\nStart with lounge access, since it's the headline perk on almost every premium card ad. Most premium cards cap you at four to six lounge visits a year before you start paying out of pocket, which only pencils out if you fly a handful of times annually. A few cards break from that pattern - Solaris Bank's Solaris Black Card, for instance, includes unlimited lounge access rather than a capped number, which matters if you travel for work more than occasionally.\n\nCashback structure is where premium cards vary the most, and where the fine print matters most. Many premium cards advertise a high headline rate - 5% or more - but cap the reward at a modest monthly ceiling, often somewhere around 15,000-20,000 AMD, after which you earn nothing extra no matter how much you spend. A flat, uncapped rate can end up paying more over a year even if the headline percentage looks smaller; the Solaris Black Card's flat 2% with no monthly ceiling is a useful reference point for what an uncapped structure looks like in practice.\n\nAnnual fees on premium cards are steep - often several times what a standard card costs - but almost every bank waives the fee if you clear a minimum annual spend. Check that threshold carefully before applying, since some banks set it high enough that only genuinely heavy card users will ever hit it. Solaris Bank's published waiver threshold for the Black Card sits noticeably lower than several competitors, which is worth factoring in if your spending is closer to average than to heavy.\n\nBundled travel insurance is another common premium perk, and coverage varies a lot in what's actually included - trip cancellation, baggage loss, and medical coverage abroad aren't guaranteed just because a card calls itself \"premium.\" The Solaris Black Card's bundled policy covers all three, which isn't universal among cards in this tier.\n\nThe honest bottom line: a premium card earns its fee back through lounge access and cashback if you travel a few times a year and spend well above what you'd put on a standard card. If neither applies to you yet, a standard no-fee card will very likely leave you ahead.",
            ],
            [
                'slug' => 'premium-credit-cards-in-armenia-worth-the-fee-hy',
                'language' => 'hy',
                'featured_image' => $cardsImage,
                'title' => 'Պրեմիում Բանկային Քարտեր Հայաստանում. Արժե՞ արդյոք վճարել տարեկան վճարը',
                'excerpt' => 'Օդանավակայանի սպասասրահ, կոնսիերժ ծառայություն, ավելի բարձր քեշբեք սահմաններ. պրեմիում քարտերը շատ բան են խոստանում։ Ահա թե ինչպես հասկանալ, թե արդյոք տարեկան վճարն իրապես իրեն արդարացնում է։',
                'body' => "Պրեմիում և «մետաղական» բանկային քարտերն այժմ Հայաստանում մեծ ուշադրության են արժանանում գովազդում՝ խոստանալով օդանավակայանի սպասասրահի մուտք, անձնական կոնսիերժ գիծ և ավելի առատաձեռն քեշբեք, քան սովորական քարտն է առաջարկում։ Իրական հարցը կայանում է ոչ թե դրանում, թե արդյոք առավելություններն իրական են՝ դրանք սովորաբար իրական են՝ այլ որքանով են դրանք արժե այն, ինչ բանկը գանձում է դրանց դիմաց։\n\nՍկսենք սպասասրահի մուտքից, քանի որ դա գրեթե ամեն պրեմիում քարտի գովազդում գլխավոր առավելությունն է։ Պրեմիում քարտերի մեծ մասը սահմանափակում է տարեկան չորսից վեց այցով, որից հետո սկսում եք վճարել ձեր գրպանից՝ ինչը իրեն արդարացնում է միայն այն դեպքում, եթե տարեկան մի քանի անգամ եք թռչում։ Որոշ քարտեր տարբերվում են այս օրինաչափությունից. օրինակ՝ Solaris Bank-ի Solaris Black Card-ը ներառում է անսահմանափակ սպասասրահի մուտք՝ սահմանափակ թվի փոխարեն, ինչը կարևոր է, եթե աշխատանքային նպատակով հաճախակի եք ճամփորդում։\n\nՔեշբեքի կառուցվածքն այն է, որտեղ պրեմիում քարտերն ամենաշատն են տարբերվում, և որտեղ մանր տառերն ամենակարևորն են։ Շատ պրեմիում քարտեր գովազդում են բարձր տոկոս՝ 5% կամ ավելի, բայց սահմանափակում են պարգևատրումը համեստ ամսական առաստաղով՝ հաճախ մոտավորապես 15,000-20,000 դրամի սահմաններում, որից հետո լրացուցիչ ոչինչ չեք վաստակում՝ անկախ նրանից, թե որքան եք ծախսում։ Հարթ, առանց սահմանափակման տոկոսադրույքը կարող է տարվա ընթացքում ավելի շատ վճարել, նույնիսկ եթե գլխավոր տոկոսը ավելի փոքր է թվում. Solaris Black Card-ի հարթ 2%-ը՝ առանց ամսական առաստաղի, օգտակար հղման կետ է հասկանալու համար, թե ինչպիսին է չսահմանափակված կառուցվածքն իրականում։\n\nՊրեմիում քարտերի տարեկան վճարները բարձր են՝ հաճախ մի քանի անգամ գերազանցելով սովորական քարտի արժեքը, բայց գրեթե ամեն բանկ չեղարկում է վճարը, եթե հասնում եք նվազագույն տարեկան ծախսի շեմին։ Ուշադիր ստուգեք այդ շեմը դիմելուց առաջ, քանի որ որոշ բանկեր այն սահմանում են այնքան բարձր, որ միայն իսկապես ակտիվ քարտի օգտագործողները երբևէ կհասնեն դրան։ Solaris Bank-ի հրապարակած շեմը Black Card-ի համար նկատելիորեն ցածր է մի քանի մրցակիցներից, ինչը արժե հաշվի առնել, եթե ձեր ծախսերն ավելի մոտ են միջինին, քան բարձրին։\n\nՆերառված ճամփորդական ապահովագրությունը մեկ այլ տարածված պրեմիում առավելություն է, և ծածկույթը մեծապես տարբերվում է նրանով, թե ինչն է իրականում ներառված. ուղևորության չեղարկումը, ուղեբեռի կորուստը և արտերկրում բժշկական ծածկույթը երաշխավորված չեն միայն այն պատճառով, որ քարտն իրեն «պրեմիում» է անվանում։ Solaris Black Card-ի ներառված քաղաքականությունը ծածկում է երեքն էլ՝ ինչը տարածված չէ այս կարգի քարտերի շրջանում։\n\nԱզնիվ եզրակացությունը հետևյալն է. պրեմիում քարտն իր վճարը հետ է վաստակում սպասասրահի մուտքի և քեշբեքի միջոցով, եթե տարեկան մի քանի անգամ եք ճամփորդում և ծախսում եք զգալիորեն ավելի, քան սովորական քարտով կդնեիք։ Եթե սա դեռ Ձեզ չի վերաբերում, առանց վճարի սովորական քարտը շատ հավանական է, որ Ձեզ ավելի շահեկան դիրքում կթողնի։",
            ],
            [
                'slug' => 'premium-credit-cards-in-armenia-worth-the-fee-ru',
                'language' => 'ru',
                'featured_image' => $cardsImage,
                'title' => 'Премиальные Кредитные Карты в Армении: Стоит ли Годовая Плата Того?',
                'excerpt' => 'Доступ в бизнес-залы, консьерж-сервис, повышенные лимиты кэшбэка - премиальные карты обещают многое. Вот как понять, окупается ли годовая плата на самом деле.',
                'body' => "Премиальные и «металлические» кредитные карты сейчас активно рекламируются в Армении, каждая обещает какое-то сочетание доступа в бизнес-залы аэропортов, личной консьерж-линии и более щедрого кэшбэка, чем у обычной карты. Реальный вопрос не в том, реальны ли эти преимущества - обычно они реальны, - а в том, стоят ли они того, что банк за них берёт.\n\nНачнём с доступа в бизнес-залы, поскольку это главное преимущество почти в каждой рекламе премиальной карты. Большинство премиальных карт ограничивают вас четырьмя-шестью посещениями в год, после чего вы начинаете платить из своего кармана - что окупается только если вы летаете хотя бы несколько раз в год. Некоторые карты отходят от этой схемы - например, Solaris Black Card от Solaris Bank включает неограниченный доступ в залы вместо ограниченного числа посещений, что важно, если вы часто летаете по работе.\n\nСтруктура кэшбэка - это то, в чём премиальные карты различаются больше всего, и где мелкий шрифт имеет наибольшее значение. Многие премиальные карты рекламируют высокую заявленную ставку - 5% и выше, - но ограничивают вознаграждение скромным месячным потолком, часто около 15 000-20 000 драм, после чего вы не зарабатываете ничего дополнительного, сколько бы ни тратили. Плоская ставка без ограничения может в итоге принести больше за год, даже если заявленный процент выглядит меньше; плоские 2% без месячного потолка у Solaris Black Card - полезный ориентир того, как выглядит структура без ограничений на практике.\n\nГодовая плата за премиальные карты высока - часто в несколько раз выше, чем за обычную карту, - но почти каждый банк отменяет плату, если вы достигаете минимального годового оборота. Внимательно проверьте этот порог перед подачей заявки, поскольку некоторые банки устанавливают его настолько высоким, что достичь его смогут только по-настоящему активные держатели карт. Опубликованный порог Solaris Bank для Black Card заметно ниже, чем у нескольких конкурентов, что стоит учитывать, если ваши траты ближе к средним, чем к высоким.\n\nВстроенное туристическое страхование - ещё одна распространённая премиальная опция, и покрытие сильно различается по тому, что на самом деле включено: отмена поездки, потеря багажа и медицинское покрытие за границей не гарантированы только потому, что карта называет себя «премиальной». Встроенный полис Solaris Black Card покрывает все три пункта, что не универсально для карт этого уровня.\n\nЧестный вывод такой: премиальная карта окупает свою плату за счёт доступа в залы и кэшбэка, если вы летаете несколько раз в год и тратите заметно больше, чем потратили бы по обычной карте. Если ни то, ни другое пока не про вас, обычная карта без платы, скорее всего, окажется выгоднее.",
            ],

            // --- Topic 2: Cash vs. card abroad ---
            [
                'slug' => 'cash-vs-card-where-you-lose-money-on-foreign-currency',
                'language' => 'en',
                'featured_image' => $exchangeImage,
                'title' => 'Cash vs. Card: Where You Actually Lose Money on Foreign Currency in Armenia',
                'excerpt' => "Paying by card abroad feels effortless - and that's exactly how the fees stay hidden. Here's where the money actually goes.",
                'body' => "Comparing bank exchange rates before a trip is a habit a lot of people already have. Fewer people apply the same scrutiny to how they actually pay once they're there, which is often where more money quietly disappears.\n\nThe first trap is dynamic currency conversion. When a foreign card terminal or ATM asks \"would you like to pay in AMD or in the local currency,\" choosing your home currency (AMD) almost always means a worse exchange rate than letting your card issuer handle the conversion. The terminal operator sets that rate, not your bank, and it's rarely competitive. The rule of thumb is simple: always choose to pay in the local currency, never your own.\n\nThe second cost is your card network's own conversion markup, which is separate from any fee your bank charges. This is usually a small percentage - often under 2% - but it applies to every single transaction, and it adds up over a week of meals, transport, and shopping in a way a single large cash exchange doesn't.\n\nCash avoids both of those costs entirely, but introduces its own: the spread between a bank's buy and sell rate, which is effectively the \"fee\" for exchanging in the first place, plus the practical risk and inconvenience of carrying a large amount of physical currency.\n\nFor most trips, a mix works best: exchange a moderate amount of cash before or shortly after arrival for day-to-day spending and emergencies, and use a card with a low or no foreign transaction fee for larger purchases where the rate matters more. Comparing which of your bank's cards actually waives the foreign transaction fee - not all of them do, even at the same bank - is worth five minutes before any trip.",
            ],
            [
                'slug' => 'cash-vs-card-where-you-lose-money-on-foreign-currency-hy',
                'language' => 'hy',
                'featured_image' => $exchangeImage,
                'title' => 'Կանխիկ թե Քարտ. Որտեղ եք իրականում կորցնում գումար արտարժույթի վրա Հայաստանում',
                'excerpt' => 'Արտերկրում քարտով վճարելը հեշտ է թվում, և հենց դա է թույլ տալիս վճարներին մնալ աննկատ։ Ահա թե որտեղ է իրականում գնում գումարը։',
                'body' => "Ճամփորդությունից առաջ բանկերի փոխարժեքները համեմատելը արդեն շատերի սովորությունն է։ Ավելի քիչ մարդիկ նույն ուշադրությունն են դարձնում այն ​​բանին, թե ինչպես են իրականում վճարում այնտեղ գտնվելիս, ինչը հաճախ հենց այնտեղ է, որտեղ ավելի շատ գումար է աննկատ անհետանում։\n\nԱռաջին թակարդը դինամիկ արժույթի փոխարկումն է։ Երբ արտասահմանյան տերմինալը կամ բանկոմատը հարցնում է՝ «վճարե՞լ դրամով, թե՞ տեղական արժույթով», ձեր սեփական արժույթը (դրամ) ընտրելը գրեթե միշտ նշանակում է ավելի վատ փոխարժեք, քան թողնելը, որ Ձեր բանկը կատարի փոխարկումը։ Այդ փոխարժեքը սահմանում է տերմինալի օպերատորը, ոչ թե Ձեր բանկը, և այն հազվադեպ է մրցունակ լինում։ Կանոնը պարզ է. միշտ ընտրեք վճարել տեղական արժույթով, երբեք՝ ձերով։\n\nԵրկրորդ ծախսը Ձեր քարտային ցանցի սեփական փոխարկման հավելավճարն է, որը առանձին է Ձեր բանկի գանձած ցանկացած վճարից։ Սա սովորաբար փոքր տոկոս է՝ հաճախ 2%-ից պակաս, բայց այն կիրառվում է յուրաքանչյուր գործարքի նկատմամբ, և կուտակվում է շաբաթվա ընթացքում կերակուրների, տրանսպորտի և գնումների վրա այնպես, ինչպես մեկ մեծ կանխիկ փոխանակումը չի կուտակվում։\n\nԿանխիկն ամբողջությամբ խուսափում է այս երկու ծախսերից, բայց ունի իր սեփականը՝ բանկի գնման և վաճառքի փոխարժեքների միջև տարբերությունը, որն ըստ էության «վճարն» է հենց փոխանակման համար, գումարած մեծ քանակությամբ կանխիկ գումար կրելու գործնական ռիսկն ու անհարմարությունը։\n\nՇատ ճամփորդությունների համար լավագույնս աշխատում է խառնուրդը՝ ժամանման ժամանակ կամ դրանից անմիջապես հետո փոխանակեք չափավոր քանակությամբ կանխիկ ամենօրյա ծախսերի և արտակարգ իրավիճակների համար, և օգտագործեք ցածր կամ առանց արտարժութային գործարքի վճարի քարտ ավելի մեծ գնումների համար, որտեղ փոխարժեքն ավելի կարևոր է։ Ստուգելը, թե Ձեր բանկի որ քարտերն են իրականում չեղարկում արտարժութային գործարքի վճարը՝ ոչ բոլորն են դա անում, նույնիսկ նույն բանկում՝ արժե հինգ րոպե ցանկացած ճամփորդությունից առաջ։",
            ],
            [
                'slug' => 'cash-vs-card-where-you-lose-money-on-foreign-currency-ru',
                'language' => 'ru',
                'featured_image' => $exchangeImage,
                'title' => 'Наличные или Карта: Где Вы Реально Теряете Деньги на Иностранной Валюте в Армении',
                'excerpt' => 'Платить картой за границей кажется простым - и именно поэтому комиссии остаются незаметными. Вот куда на самом деле уходят деньги.',
                'body' => "Сравнивать курсы обмена в банках перед поездкой - уже привычка для многих. Гораздо меньше людей уделяют такое же внимание тому, как они на самом деле платят, оказавшись на месте, а ведь именно там незаметно теряется больше денег.\n\nПервая ловушка - динамическая конвертация валюты. Когда иностранный терминал или банкомат спрашивает «оплатить в драмах или в местной валюте», выбор своей валюты (драм) почти всегда означает худший курс, чем если позволить банку-эмитенту самому провести конвертацию. Этот курс устанавливает оператор терминала, а не ваш банк, и он редко бывает конкурентным. Правило простое: всегда выбирайте оплату в местной валюте, никогда - в своей.\n\nВторая статья расходов - собственная надбавка платёжной системы за конвертацию, отдельная от любой комиссии банка. Обычно это небольшой процент - часто меньше 2%, - но он применяется к каждой транзакции и накапливается за неделю на еде, транспорте и покупках так, как не накапливается один крупный обмен наличных.\n\nНаличные полностью избегают обеих этих затрат, но вносят свою: разницу между курсом покупки и продажи банка, которая по сути и есть «плата» за сам обмен, плюс практический риск и неудобство перевозки крупной суммы наличных.\n\nДля большинства поездок лучше всего работает комбинация: обменять умеренную сумму наличными по прибытии или вскоре после для повседневных трат и непредвиденных ситуаций, и использовать карту с низкой или нулевой комиссией за иностранные транзакции для более крупных покупок, где курс важнее. Проверить, какие карты вашего банка действительно отменяют комиссию за иностранные транзакции - не все это делают, даже в одном банке, - стоит потратить пять минут перед любой поездкой.",
            ],

            // --- Topic 3: Bundling auto + home insurance ---
            [
                'slug' => 'bundling-auto-and-home-insurance-in-armenia',
                'language' => 'en',
                'featured_image' => $insuranceImage,
                'title' => 'Bundling Auto and Home Insurance in Armenia: Does It Actually Save Money?',
                'excerpt' => 'Multi-policy discounts sound like an easy win. Sometimes they are - and sometimes a cheaper bundle quietly locks you into weaker coverage.',
                'body' => "Insurers advertise bundling discounts heavily, and the pitch is straightforward: put your auto and home policies with the same company and get a percentage off both. The savings are usually real, typically somewhere in the 5-15% range depending on the insurer - but whether bundling is actually the better move depends on more than the discount alone.\n\nThe clearest case for bundling is when one insurer is genuinely strong on both fronts - competitive pricing and solid claims handling for auto, and comparable coverage and service for home. In that situation, the discount is close to free money, since you weren't giving anything up to get it.\n\nThe less obvious case is when you already have an auto insurer you're happy with - fast claims, fair pricing, good communication - and a bundling offer from a different company would mean switching away from that just to save a modest percentage. If the new insurer's claims handling turns out to be slower or their coverage terms narrower, the discount can end up costing you far more than it saved, especially if you ever need to file a claim.\n\nBefore bundling, it's worth asking a few direct questions: does the combined policy cover everything your separate policies did, or does something get scaled back to hit the discounted price? What's the insurer's actual claims-handling reputation, not just their marketing? And can you unbundle later - switch just the home or just the auto policy - without losing the discount on the one you keep, or does the whole thing collapse if you split it up again?\n\nBundling is a genuine opportunity when the math and the coverage both line up. It's worth being skeptical of a discount that only makes sense if you don't look too closely at what you're trading for it.",
            ],
            [
                'slug' => 'bundling-auto-and-home-insurance-in-armenia-hy',
                'language' => 'hy',
                'featured_image' => $insuranceImage,
                'title' => 'Ավտո և Բնակարանի Ապահովագրության Համատեղում Հայաստանում. Դա իրապես գումար խնայու՞մ է',
                'excerpt' => 'Բազմաքաղաքականության զեղչերը հեշտ հաղթանակի պես են թվում։ Երբեմն այդպես էլ կա, իսկ երբեմն ավելի էժան փաթեթը լուռ կողպում է Ձեզ ավելի թույլ ծածկույթի մեջ։',
                'body' => "Ապահովագրողները ինտենսիվ գովազդում են համատեղման զեղչերը, և առաջարկը պարզ է. տեղադրեք Ձեր ավտո և բնակարանի քաղաքականությունները նույն ընկերությունում և ստացեք զեղչ երկուսի վրա էլ։ Խնայողությունը սովորաբար իրական է՝ սովորաբար 5-15% սահմաններում՝ կախված ապահովագրողից, բայց արդյոք համատեղումն իրապես ավելի լավ քայլ է, կախված է ավելիից, քան միայն զեղչից։\n\nԱմենապարզ դեպքն այն է, երբ մեկ ապահովագրողն իրապես ուժեղ է երկու ուղղություններով էլ՝ մրցունակ գներ և հուսալի հայցերի մշակում ավտոյի համար, և համադրելի ծածկույթ ու սպասարկում բնակարանի համար։ Այս իրավիճակում զեղչը գրեթե անվճար գումար է, քանի որ ոչինչ չեք զիջում այն ​​ստանալու համար։\n\nԱվելի քիչ ակնհայտ դեպքն այն է, երբ Դուք արդեն ունեք ավտոապահովագրող, որով գոհ եք՝ արագ հայցեր, արդար գներ, լավ հաղորդակցում, և մեկ այլ ընկերությունից համատեղման առաջարկը կնշանակի հեռանալ դրանից՝ միայն համեստ տոկոս խնայելու համար։ Եթե նոր ապահովագրողի հայցերի մշակումն ավելի դանդաղ է, կամ նրանց ծածկույթի պայմաններն ավելի նեղ են, զեղչը կարող է վերջում ավելի շատ արժենալ, քան խնայել է, հատկապես, եթե երբևէ հայց ներկայացնեք։\n\nՀամատեղումից առաջ արժե տալ մի քանի ուղղակի հարց. արդյոք համակցված քաղաքականությունն ամեն ինչ ծածկում է, ինչ ծածկում էին առանձին քաղաքականությունները, թե՞ ինչ-որ բան կրճատվում է՝ զեղչված գնին հասնելու համար։ Որն է ապահովագրողի հայցերի մշակման իրական համբավը, ոչ միայն նրանց մարքեթինգը։ Եվ կարո՞ղ եք հետո առանձնացնել՝ փոխարինել միայն բնակարանի կամ միայն ավտոյի քաղաքականությունը՝ առանց կորցնելու մնացածի զեղչը, թե ամբողջը փլուզվում է, եթե կրկին առանձնացնեք դրանք։\n\nՀամատեղումը իրական հնարավորություն է, երբ և՛ հաշվարկը, և՛ ծածկույթը համընկնում են։ Արժե կասկածամիտ լինել զեղչի նկատմամբ, որը իմաստ ունի միայն այն դեպքում, եթե շատ մոտից չնայեք, թե ինչի եք փոխանակում այն։",
            ],
            [
                'slug' => 'bundling-auto-and-home-insurance-in-armenia-ru',
                'language' => 'ru',
                'featured_image' => $insuranceImage,
                'title' => 'Объединение Автострахования и Страхования Жилья в Армении: Реально ли Это Экономит?',
                'excerpt' => 'Скидки за несколько полисов звучат как лёгкая выгода. Иногда так и есть - а иногда более дешёвый пакет незаметно закрепляет за вами более слабое покрытие.',
                'body' => "Страховщики активно рекламируют скидки за объединение полисов, и предложение простое: оформите автострахование и страхование жилья в одной компании и получите скидку на оба. Экономия обычно реальна - как правило, в диапазоне 5-15% в зависимости от страховщика, - но действительно ли объединение лучший вариант, зависит от большего, чем просто скидка.\n\nСамый ясный случай для объединения - когда один страховщик действительно силён по обоим направлениям: конкурентные цены и надёжная обработка выплат по авто, и сопоставимое покрытие и сервис по жилью. В этой ситуации скидка - почти бесплатные деньги, поскольку вы ничем не жертвуете, чтобы её получить.\n\nМенее очевидный случай - когда у вас уже есть автостраховщик, которым вы довольны: быстрые выплаты, честные цены, хорошая коммуникация, - а предложение об объединении от другой компании означало бы уйти от этого ради скромного процента экономии. Если обработка выплат у нового страховщика окажется медленнее, а условия покрытия - уже, скидка в итоге может стоить намного больше, чем сэкономила, особенно если вам когда-нибудь понадобится подать claim.\n\nПеред объединением стоит задать несколько прямых вопросов: покрывает ли объединённый полис всё, что покрывали отдельные полисы, или что-то урезается, чтобы выйти на льготную цену? Какова реальная репутация страховщика по обработке выплат, а не только его маркетинг? И можно ли потом разделить полисы - заменить только жильё или только авто - не потеряв скидку на оставшемся, или всё рушится, если разделить их снова?\n\nОбъединение - реальная возможность, когда совпадают и математика, и покрытие. Стоит скептически относиться к скидке, которая имеет смысл только если не присматриваться слишком внимательно к тому, чем вы за неё платите.",
            ],

            // --- Topic 4: Planning an international trip ---
            [
                'slug' => 'planning-an-international-trip-from-armenia',
                'language' => 'en',
                'featured_image' => $travelImage,
                'title' => 'Planning an International Trip from Armenia: Insurance, Currency, and Costs to Sort Out First',
                'excerpt' => "The parts of trip planning that don't involve booking flights or hotels - but end up mattering just as much once you're actually there.",
                'body' => "Flights and hotels get most of the attention when planning a trip abroad, but a handful of less exciting decisions - insurance, currency, and how you'll actually pay for things - tend to matter just as much once you've landed.\n\nTravel insurance is the one people skip most often, usually because it feels like an unlikely-to-matter expense until the moment it very much matters. At minimum, look for coverage on medical treatment abroad (which can be extremely expensive in countries without a reciprocal healthcare agreement with Armenia), trip cancellation, and lost or delayed baggage. Some credit cards bundle a version of this coverage automatically - worth checking before buying a separate policy that duplicates what you already have.\n\nOn currency, the best approach is usually a mix: exchange a moderate amount of cash before or shortly after arrival for the first day or two, and rely on a card with low foreign transaction fees for the rest of the trip. Comparing exchange rates and card fees before you go, rather than reacting once you're already there, is where most of the achievable savings actually come from.\n\nBudgeting for a trip abroad is easier when you separate costs into what's fixed (flights, accommodation, insurance) and what's variable (food, activities, shopping) - the variable side is where costs quietly run over, especially in destinations with a much higher cost of living than Armenia.\n\nFinally, if you're working with a travel agency rather than booking everything yourself, getting quotes from more than one agency for the same trip is worth the extra day it takes - itineraries that look similar on paper can differ meaningfully in what's actually included once you compare them side by side.",
            ],
            [
                'slug' => 'planning-an-international-trip-from-armenia-hy',
                'language' => 'hy',
                'featured_image' => $travelImage,
                'title' => 'Միջազգային Ուղևորության Պլանավորում Հայաստանից. Ապահովագրություն, Արժույթ և Ծախսեր, Որոնք Պետք է Կարգավորել Առաջինը',
                'excerpt' => 'Ուղևորության պլանավորման այն մասերը, որոնք թռիչքների կամ հյուրանոցների ամրագրման հետ կապ չունեն, բայց հավասարապես կարևոր են դառնում, երբ արդեն այնտեղ եք։',
                'body' => "Թռիչքներն ու հյուրանոցները ստանում են ամենամեծ ուշադրությունը արտերկիր ուղևորությունը պլանավորելիս, բայց մի քանի ավելի քիչ հետաքրքիր որոշումներ՝ ապահովագրություն, արժույթ և ինչպես եք իրականում վճարելու իրերի համար, հակված են հավասարապես կարևոր լինել, երբ արդեն վայրէջք եք կատարել։\n\nՃամփորդական ապահովագրությունն այն է, ինչը մարդիկ ամենահաճախ բաց են թողնում, սովորաբար այն պատճառով, որ դա անհավանական ծախս է թվում, մինչև այն պահը, երբ շատ կարևոր է դառնում։ Նվազագույնը փնտրեք ծածկույթ արտերկրում բժշկական բուժման համար (որը կարող է չափազանց թանկ լինել այն երկրներում, որոնք Հայաստանի հետ փոխադարձ առողջապահական համաձայնագիր չունեն), ուղևորության չեղարկման և ուղեբեռի կորստի կամ ուշացման համար։ Որոշ բանկային քարտեր ավտոմատ կերպով ներառում են այս ծածկույթի ինչ-որ տարբերակ. արժե ստուգել դա՝ նախքան առանձին քաղաքականություն գնելը, որը կրկնում է այն, ինչ արդեն ունեք։\n\nԱրժույթի հարցում լավագույն մոտեցումը սովորաբար խառնուրդն է. ժամանման ժամանակ կամ դրանից անմիջապես հետո փոխանակեք չափավոր քանակությամբ կանխիկ առաջին օր-երկուսի համար, և մնացած ուղևորության համար հենվեք ցածր արտարժութային վճարով քարտի վրա։ Մեկնելուց առաջ փոխարժեքներն ու քարտի վճարները համեմատելը, այլ ոչ թե արձագանքելը, երբ արդեն այնտեղ եք, այն է, որտեղից իրականում գալիս է հասանելի խնայողությունների մեծ մասը։\n\nԱրտերկիր ուղևորության համար բյուջե կազմելը ավելի հեշտ է, երբ ծախսերն առանձնացնում եք ֆիքսվածի (թռիչքներ, կացարան, ապահովագրություն) և փոփոխականի (սնունդ, միջոցառումներ, գնումներ)՝ հենց փոփոխական կողմն է, որ լուռ գերազանցում է սպասվածը, հատկապես Հայաստանից շատ ավելի բարձր կենսամակարդակ ունեցող նպատակակետերում։\n\nՎերջում, եթե աշխատում եք ճամփորդական գործակալության հետ, այլ ոչ թե ամեն ինչ ինքներդ եք ամրագրում, նույն ուղևորության համար մեկից ավելի գործակալությունից գնանշում ստանալը արժե այն լրացուցիչ օրը, որ պահանջում է. թղթի վրա նման տեսք ունեցող երթուղիները կարող են էապես տարբերվել, երբ իրականում համեմատեք, թե ինչ է ներառված։",
            ],
            [
                'slug' => 'planning-an-international-trip-from-armenia-ru',
                'language' => 'ru',
                'featured_image' => $travelImage,
                'title' => 'Планирование Международной Поездки из Армении: Страхование, Валюта и Расходы, Которые Нужно Уладить в Первую Очередь',
                'excerpt' => 'Часть подготовки к поездке, не связанная с бронированием рейсов или отелей - но не менее важная, когда вы уже на месте.',
                'body' => "Рейсы и отели получают большую часть внимания при планировании поездки за границу, но несколько менее увлекательных решений - страхование, валюта и то, как вы на самом деле будете платить, - оказываются не менее важны, когда вы уже приземлились.\n\nТуристическую страховку пропускают чаще всего, обычно потому что она кажется маловероятно нужным расходом - до момента, когда она становится очень нужной. Как минимум ищите покрытие медицинского лечения за границей (которое может быть крайне дорогим в странах без соглашения о взаимном медицинском обслуживании с Арменией), отмены поездки и потери или задержки багажа. Некоторые кредитные карты автоматически включают версию такого покрытия - стоит проверить это перед покупкой отдельного полиса, дублирующего то, что у вас уже есть.\n\nПо валюте лучший подход обычно - комбинация: обменять умеренную сумму наличными по прибытии или вскоре после для первых одного-двух дней, и полагаться на карту с низкой комиссией за иностранные транзакции для остальной части поездки. Сравнение курсов обмена и комиссий карт перед поездкой, а не реакция на месте, - вот откуда на самом деле берётся большая часть доступной экономии.\n\nБюджетировать поездку за границу проще, если разделить расходы на фиксированные (рейсы, проживание, страхование) и переменные (еда, развлечения, покупки) - именно переменная часть незаметно превышает ожидания, особенно в направлениях с намного более высокой стоимостью жизни, чем в Армении.\n\nНаконец, если вы работаете с туристическим агентством, а не бронируете всё сами, стоит потратить лишний день на получение предложений от нескольких агентств для одной и той же поездки: маршруты, выглядящие похоже на бумаге, могут существенно отличаться, когда вы на самом деле сравните, что в них включено.",
            ],
        ];
    }
}
