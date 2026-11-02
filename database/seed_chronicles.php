<?php
declare(strict_types=1);
// Adds or refreshes the sample Gulu Chronicles stories: short original write-ups with an original
// illustration, credit to the outlet that reported the news, and a link to the original report.
// Safe to run more than once: stories are matched by source_url.
require __DIR__ . '/../app/bootstrap.php';

$stories = [
    ['Uganda News', 'news', 'Museveni cautions MPs over digital number plates reports', '2026-11-02 09:00:00', 'The Independent (Kampala)', 'https://allafrica.com/stories/202611020057.html', 'img/news/plates.svg',
     "President Yoweri Museveni has warned members of parliament against circulating inaccurate reports about the digital number plates programme, according to The Independent (Kampala), which published its report on 2 November 2026.\n\n" .
     "What we know: the President's message was aimed at lawmakers who have been discussing the programme in public. He asked them to rely on accurate information before sharing claims about it.\n\n" .
     "Background: digital number plates are linked to Uganda's Intelligent Transport Monitoring System (ITMS), which is meant to help track vehicles. On 30 October, Nile Post reported that the President also asked the ITMS contractor to explain a cost increase and gaps in tracking.\n\n" .
     "Why it matters: the plates affect every vehicle owner, so questions about cost, tracking and who benefits are likely to keep coming up in Parliament and in the media.\n\n" .
     "Read the original report linked below for the full details and exact wording."],
    ['Entertainment', 'news', 'Comedian Kapere Lufotose has died', '2026-11-02 07:30:00', 'Nile Post', 'https://allafrica.com/stories/202611020054.html', 'img/news/comedy.svg',
     "Nile Post reports that Ugandan comedian Kapere Lufotose has died. The report was published on 2 November 2026.\n\n" .
     "What we know: the news was reported by Nile Post and carried by allAfrica on 2 November. The Gulu Chronicles has not independently confirmed further details, so we are not sharing any we cannot verify.\n\n" .
     "Why it matters: comedy is a loved part of Uganda's entertainment scene, and the death of a performer is felt by fans, fellow comedians and event organisers across the country, including the stages that bring laughter to Gulu.\n\n" .
     "The Gulu Chronicles sends condolences to his family, friends and fans. We will add information from the family and official sources when it is shared. Please read the original report linked below."],
    ['Uganda News', 'news', 'Government says it is working with Uganda on Lake Victoria harassment', '2026-11-02 08:00:00', 'Capital FM (via allAfrica)', 'https://allafrica.com/stories/202611020037.html', 'img/news/lake.svg',
     "Permanent Secretary Omollo says the government is engaging Uganda to find a permanent solution to harassment on Lake Victoria, Capital FM reported on 2 November 2026.\n\n" .
     "What we know: the statement is about a lasting fix, not a one-off response. The report does not set out a timeline in the headline we reviewed, so readers should check the original for specifics.\n\n" .
     "Background: Lake Victoria is shared by Uganda, Kenya and Tanzania. Millions of people depend on it for fishing, trade and transport, so incidents on the water quickly affect families and local markets.\n\n" .
     "Why it matters: a clear arrangement between neighbouring governments helps fishing communities work without fear, and keeps regional trade moving.\n\n" .
     "We will update this story as more official information is released."],
    ['Business', 'news', "Uganda's trade deficit with East African partners widens to \$809 million", '2026-10-30 12:00:00', 'The Independent (Kampala)', 'https://allafrica.com/stories/202610300235.html', 'img/news/trade.svg',
     "Uganda's trade deficit with its East African Community partners has grown to \$809 million as imports surge, The Independent (Kampala) reported on 30 October 2026.\n\n" .
     "What we know: the figure is the gap between what Uganda buys from its regional partners and what it sells to them. The report says imports are rising.\n\n" .
     "Explainer: a trade deficit is not always bad on its own, for example when imports are machinery that helps businesses grow. It becomes a concern when it keeps widening and puts pressure on the currency and on foreign exchange reserves.\n\n" .
     "Why it matters: for traders in Gulu and across Northern Uganda, a wider deficit can mean costlier imported goods and a weaker shilling.\n\n" .
     "The original report has the full figures and analysis."],
    ['Business', 'news', "Museveni asks Egyptian investors to back Uganda's infrastructure", '2026-10-30 11:00:00', 'Nile Post', 'https://allafrica.com/stories/202610300184.html', 'img/news/invest.svg',
     "President Museveni is encouraging Egyptian investors to finance infrastructure and value addition projects in Uganda, according to Nile Post on 30 October 2026.\n\n" .
     "What we know: the President's call covers two areas, infrastructure such as roads and energy, and value addition.\n\n" .
     "Explainer: value addition means processing raw products such as farm produce and minerals inside Uganda instead of exporting them unprocessed. It creates jobs and keeps more of the profit in the country.\n\n" .
     "Why it matters: Northern Uganda grows crops such as sunflower, cotton and cereals, so processing investment could open new markets and jobs for farmers and young people.\n\n" .
     "Read the original report for the full details."],
    ['Business', 'opinion', 'Opinion: the shilling crisis and the cost of growth without better livelihoods', '2026-10-30 10:00:00', 'The Independent (Kampala)', 'https://allafrica.com/stories/202610300307.html', 'img/news/shilling.svg',
     "An opinion piece in The Independent (Kampala) on 30 October 2026 argues that the recent fall of the Uganda shilling shows the cost of economic growth that has not improved people's day-to-day livelihoods.\n\n" .
     "The argument: growth figures can look healthy while households still struggle with the price of food, fuel and school fees. When the shilling weakens, imported goods become more expensive and the pressure falls hardest on families with the least.\n\n" .
     "Background: a weaker shilling raises the cost of imports, and it also makes debts that are owed in foreign currency more expensive to repay.\n\n" .
     "This is the writer's view, not a statement of fact by The Gulu Chronicles. Read the full opinion at the link below and form your own view."],
];

$ins = $upd = 0;
foreach ($stories as [$cat, $label, $title, $when, $src, $url, $img, $body]) {
    $cid = val('SELECT id FROM article_categories WHERE name = ?', [$cat]) ?: null;
    $excerpt = mb_substr(explode("\n", $body)[0], 0, 300);
    if ($id = val('SELECT id FROM articles WHERE source_url = ?', [$url])) {
        q('UPDATE articles SET category_id=?, title=?, excerpt=?, body=?, label=?, image=?, source_name=? WHERE id=?', [$cid, $title, $excerpt, $body, $label, $img, $src, $id]);
        $upd++;
        continue;
    }
    q("INSERT INTO articles (category_id, title, excerpt, body, label, status, published_at, slug, source_name, source_url, image) VALUES (?,?,?,?,?,'published',?,?,?,?,?)",
        [$cid, $title, $excerpt, $body, $label, $when, unique_slug('articles', $title), $src, $url, $img]);
    $ins++;
}
echo "Inserted $ins, updated $upd stories\n";
