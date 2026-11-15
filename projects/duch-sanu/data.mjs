// Duch Sanu – dane sklepu demo (stan oferty duchsanu.com, 15.11.2026)
// Ceny, % alkoholu, ekstrakt, chmiele i dodatki – z kart produktów sklepu.
// Profil smaku (1–5), nuty i parowanie – PROPOZYCJE do potwierdzenia z browarem.

export const FAMILIES = [
  { id: 'rzeskie', name: 'Rześkie i jasne', color: '#D9B44A', dark: true },
  { id: 'owocowe', name: 'Miodowe i owocowe', color: '#C2556A' },
  { id: 'ciemne', name: 'Ciemne i mocne', color: '#2A211C' },
  { id: 'ipa', name: 'Chmielowe IPA', color: '#4F6B4A' },
  { id: 'zero', name: 'Bez alkoholu', color: '#6F98A0' },
  { id: 'bezglutenu', name: 'Bez glutenu', color: '#B8A35A', dark: true },
];

const BASE_HOPS = 'Magnum, Hallertauer Tradition, Sybilla';
const OPEN = 'Warzone tradycyjną metodą w otwartych kadziach.';

// f: [goryczka, słodowość, owocowość, ciało, palone]
export const BEERS = [
  { slug: 'jasne', name: 'Jasne', style: 'Jasny lager', abv: '5,2', ext: '11', price: 8.99, color: '#D9B44A', fam: ['rzeskie'], series: 'Klasyka', cap: 'kapsel', hops: BASE_HOPS,
    short: 'Rześkie, zbożowe, z czystą goryczką', desc: `Jasny lager, filtrowany, pasteryzowany. ${OPEN} Codzienne piwo na upał, grill i rozmowę na ganku.`, f: [2, 3, 1, 2, 1], temp: '4–6°C', glass: 'kufel', pair: ['Kiełbasa z grilla', 'Proziaki z masłem czosnkowym', 'Smażony ser'] },
  { slug: 'pils', name: 'Pils', style: 'Pils', abv: '5,4', ext: '12', price: 10.99, color: '#5E8A3A', fam: ['rzeskie'], series: 'Klasyka', hops: 'Magnum, Sybilla, Hallertauer Tradition',
    short: 'Ziołowy chmiel, wytrawny finisz', desc: `Jasny lager, filtrowany, pasteryzowany. ${OPEN} Wyraźniej chmielony, ziołowy, z wytrawnym końcem.`, f: [3, 2, 1, 2, 1], temp: '5–7°C', glass: 'pokal do pilsa', pair: ['Pstrąg z grilla', 'Sałatka z rzodkiewką', 'Ryba w panierce'] },
  { slug: 'meski-lager', name: 'Męski Lager', style: 'Lager', abv: '6,1', price: 10.99, color: '#9A7440', fam: ['rzeskie'], series: 'Klasyka', hops: BASE_HOPS,
    short: 'Pełny, słodowy, z wyraźnym ciałem', desc: `Lager o pełniejszym ciele i mocniejszym charakterze. ${OPEN}`, f: [2, 4, 1, 3, 1], temp: '6–8°C', glass: 'kufel', pair: ['Golonka', 'Pieczone żeberka', 'Ser wędzony'] },
  { slug: 'polskie-chmiele', name: 'Polskie Chmiele', style: 'Lager', abv: '5,5', ext: '12', price: 10.99, color: '#6E8B3D', fam: ['rzeskie'], series: 'Klasyka', hops: 'Puławski, Sybilla, Octawia',
    short: 'Trzy polskie chmiele, kwiatowo-korzenny', desc: `Jasny lager, filtrowany, pasteryzowany, chmielony wyłącznie polskimi odmianami. ${OPEN}`, f: [3, 2, 2, 2, 1], temp: '5–7°C', glass: 'pokal', pair: ['Pierogi ruskie', 'Twarożek ze szczypiorkiem', 'Kurczak z ziołami'] },
  { slug: 'niefiltrowane', name: 'Niefiltrowane', style: 'Lager niefiltrowany', abv: '4,7', ext: '10', price: 10.99, color: '#D9A441', fam: ['rzeskie'], series: 'Klasyka', hops: 'Magnum, Hallertauer Tradition',
    short: 'Mętne, chlebowe, z drożdżową nutą', desc: `Jasny lager, niefiltrowany, pasteryzowany. ${OPEN} Chlebowe, delikatnie owocowe od drożdży.`, f: [2, 3, 2, 2, 1], temp: '5–7°C', glass: 'kufel', pair: ['Chleb ze smalcem', 'Placki ziemniaczane', 'Bryndza'] },
  { slug: 'pszeniczne', name: 'Pszeniczne', style: 'Hefeweizen', abv: '5,2', ext: '12', price: 10.99, color: '#E0B84E', fam: ['rzeskie'], series: 'Klasyka', hops: BASE_HOPS,
    short: 'Banan, goździk, puszysta piana', desc: 'Jasne piwo w stylu Hefeweizen, niefiltrowane, pasteryzowane. Warzone metodą górnej fermentacji w otwartych kadziach.', f: [1, 3, 4, 3, 1], temp: '6–8°C', glass: 'szklanka do pszenicy', pair: ['Biała kiełbasa', 'Sałatka z owocami', 'Naleśniki'] },
  { slug: 'zakapior', name: 'Legendarny Zakapior', style: 'Mocny lager', abv: '7,2', ext: '11,5', price: 10.99, color: '#8C3B2B', fam: ['ciemne'], series: 'Klasyka', hops: BASE_HOPS,
    short: 'Karmelowy, rozgrzewający, z charakterem', desc: `Mocny jasny lager, filtrowany, pasteryzowany. ${OPEN} Dla tych, którzy schodzą z połonin po zmroku.`, f: [2, 4, 2, 4, 1], temp: '7–9°C', glass: 'kufel', pair: ['Gulasz z dziczyzny', 'Kiełbasa z ogniska', 'Oscypek z żurawiną'] },
  { slug: 'ciemne', name: 'Ciemne', style: 'Ciemny lager', abv: '5,0', ext: '12', price: 10.99, color: '#5A3A2A', fam: ['ciemne'], series: 'Klasyka', hops: BASE_HOPS,
    short: 'Palony słód, kawa, gorzka czekolada', desc: `Ciemny lager, filtrowany, pasteryzowany. ${OPEN}`, f: [2, 4, 1, 3, 4], temp: '7–9°C', glass: 'kufel', pair: ['Pieczona karkówka', 'Ser dojrzewający', 'Ciasto czekoladowe'] },
  { slug: 'czarne-zloto', name: 'Czarne Złoto', style: 'Porter bałtycki', abv: '10,2', ext: '22', price: 12.99, color: '#2A211C', fam: ['ciemne'], series: 'Klasyka', hops: BASE_HOPS, badge: 'Bestseller',
    short: 'Suszona śliwka, melasa, kakao', desc: 'Porter bałtycki, filtrowany, pasteryzowany. Warzony tradycyjną metodą dolnej fermentacji w otwartych kadziach i leżakowany 180 dni. Pij powoli – najlepiej przy kominku.', f: [2, 5, 3, 5, 5], temp: '12–14°C', glass: 'snifter', pair: ['Pieczona kaczka ze śliwkami', 'Ser pleśniowy', 'Gorzka czekolada'] },
  { slug: 'plaster-miodu', name: 'Plaster Miodu', style: 'Lager z miodem', abv: '4,7', price: 10.99, color: '#D99A2B', fam: ['owocowe'], series: 'Smakowe i miodowe', hops: BASE_HOPS, badge: 'Ulubione',
    short: '60 g miodu wielokwiatowego na litr', desc: `Jasny lager z miodem wielokwiatowym (60 g/l), filtrowany, pasteryzowany. ${OPEN}`, f: [1, 4, 3, 2, 1], temp: '5–7°C', glass: 'pokal', pair: ['Kozi ser z orzechami', 'Pieczony kurczak', 'Szarlotka'] },
  { slug: 'imbir-z-miodem', name: 'Imbir z Miodem', style: 'Lager z miodem i imbirem', abv: '4,7', price: 10.99, color: '#C9862E', fam: ['owocowe'], series: 'Smakowe i miodowe', hops: BASE_HOPS,
    short: 'Gryczany miód i pikantny imbir', desc: `Jasny lager z miodem gryczanym (60 g/l) i syropem o smaku imbiru. ${OPEN}`, f: [1, 4, 3, 2, 1], temp: '5–7°C', glass: 'pokal', pair: ['Kuchnia azjatycka', 'Pierniki', 'Łosoś pieczony'] },
  { slug: 'dzika-roza', name: 'Dzika Róża', style: 'Lager smakowy', abv: '4,7', price: 10.99, color: '#C2556A', fam: ['owocowe'], series: 'Smakowe i miodowe', hops: BASE_HOPS,
    short: 'Kwiatowa, delikatnie słodka', desc: `Jasny lager z syropem o smaku dzikiej róży. ${OPEN}`, f: [1, 3, 4, 2, 1], temp: '4–6°C', glass: 'tulipan', pair: ['Deser z bezą', 'Sałatka z burakiem', 'Twarożek'] },
  { slug: 'dziki-bez', name: 'Dziki Bez', style: 'Lager smakowy', abv: '4,7', price: 10.99, color: '#7E8F5A', fam: ['owocowe'], series: 'Smakowe i miodowe', hops: BASE_HOPS,
    short: 'Kwiat czarnego bzu, lekki i orzeźwiający', desc: `Jasny lager z syropem o smaku kwiatu czarnego bzu. ${OPEN}`, f: [1, 3, 4, 2, 1], temp: '4–6°C', glass: 'tulipan', pair: ['Lekkie sałatki', 'Ryba z cytryną', 'Sorbet'] },
  { slug: 'zurawina', name: 'Żurawina', style: 'Lager smakowy', abv: '4,7', price: 10.99, color: '#A3253A', fam: ['owocowe'], series: 'Smakowe i miodowe', hops: BASE_HOPS,
    short: 'Cierpka żurawina, rześki finisz', desc: `Jasny lager z syropem o smaku żurawiny. ${OPEN}`, f: [1, 3, 4, 2, 1], temp: '4–6°C', glass: 'tulipan', pair: ['Oscypek z żurawiną', 'Indyk pieczony', 'Pasztet'] },
  { slug: 'pigwa-malina', name: 'Pigwa-Malina', style: 'Lager smakowy', abv: '4,7', price: 10.99, color: '#D27A3B', fam: ['owocowe'], series: 'Smakowe i miodowe', cap: 'kapsel', hops: BASE_HOPS,
    short: 'Pigwa i malina w nowym opakowaniu', desc: `Nasza klasyczna Pigwa-Malina w nowym opakowaniu. Jasny lager z syropem owocowym. ${OPEN}`, f: [1, 3, 4, 2, 1], temp: '4–6°C', glass: 'tulipan', pair: ['Desery owocowe', 'Kaczka', 'Sery łagodne'] },
  { slug: 'ciemna-malina', name: 'Ciemna Malina', style: 'Ciemny lager z maliną', abv: '5,0', price: 10.99, color: '#7A2340', fam: ['ciemne', 'owocowe'], series: 'Smakowe i miodowe', hops: BASE_HOPS,
    short: 'Malina i palony słód, goryczka 1/5', desc: 'Ciemny lager z intensywnym aromatem soczystych malin. Łączy delikatne nuty palonego słodu z owocową słodyczą.', f: [1, 3, 4, 3, 3], temp: '6–8°C', glass: 'tulipan', pair: ['Brownie z malinami', 'Dziczyzna', 'Ser kozi'] },
  { slug: 'bezglutenowy-lager', name: 'Bezglutenowy Lager', style: 'Lager bezglutenowy', abv: '5,5', price: 11.99, color: '#B8A35A', fam: ['bezglutenu', 'rzeskie'], series: 'Bez glutenu', hops: '—',
    short: 'Lekki, z subtelną goryczką', desc: 'Lekkość i subtelna goryczka – bezglutenowa alternatywa tradycyjnego piwa, bez rezygnacji z jakości i smaku.', f: [2, 2, 1, 2, 1], temp: '4–6°C', glass: 'pokal', pair: ['Grillowane warzywa', 'Ryby', 'Sałatki'] },
  { slug: 'bezglutenowa-sliwka', name: 'Bezglutenowa Śliwka', style: 'Bezglutenowe ze śliwką', abv: '5,5', price: 11.99, color: '#5B3A6B', fam: ['bezglutenu', 'owocowe'], series: 'Bez glutenu', hops: '—',
    short: 'Letnia śliwka, subtelna słodycz', desc: 'Delikatny aromat śliwki i subtelna słodycz – bezpieczne dla osób unikających glutenu.', f: [1, 3, 4, 2, 1], temp: '5–7°C', glass: 'tulipan', pair: ['Placek drożdżowy', 'Wieprzowina ze śliwką', 'Sery'] },
  { slug: 'zero', name: 'Zero', style: 'Bezalkoholowe', abv: '0,0', price: 10.99, color: '#2F4A6E', fam: ['zero'], series: 'Zero', hops: BASE_HOPS,
    short: 'Pełny smak lagera bez procentów', desc: 'Piwo jasne, bezalkoholowe, filtrowane, pasteryzowane. Orzeźwiające, z pełnym smakiem.', f: [2, 2, 1, 2, 1], temp: '4–6°C', glass: 'pokal', pair: ['Na szlak', 'Do kanapek', 'Po rowerze'] },
  { slug: 'malina-zero', name: 'Malina Zero', style: 'Bezalkoholowe z maliną', abv: '0,0', price: 10.99, color: '#C2456A', fam: ['zero', 'owocowe'], series: 'Zero', hops: BASE_HOPS,
    short: 'Malina i pigwa, zero procent', desc: 'Piwo jasne, bezalkoholowe, z syropem o smaku malinowo-pigwowym.', f: [1, 3, 4, 2, 1], temp: '4–6°C', glass: 'tulipan', pair: ['Desery', 'Lody', 'Upał'] },
  { slug: 'borowka-zero', name: 'Borówka Zero', style: 'Bezalkoholowe z borówką', abv: '0,0', price: 10.99, color: '#3D4F8A', fam: ['zero', 'owocowe'], series: 'Zero', hops: BASE_HOPS,
    short: 'Leśna borówka, zero procent', desc: 'Piwo jasne, bezalkoholowe, z syropem o smaku borówkowym. Smak bieszczadzkiego lasu w sierpniu.', f: [1, 3, 4, 2, 1], temp: '4–6°C', glass: 'tulipan', pair: ['Jagodzianki', 'Naleśniki', 'Na szlak'] },
  { slug: 'karmazynowy-odyniec', name: 'Karmazynowy Odyniec', style: 'Premium · jasne z dereniem', abv: '4,7', price: 11.99, color: '#8E1F2F', fam: ['owocowe'], series: 'Premium', premium: 'No. 02 · Odyniec', hops: BASE_HOPS, badge: 'Premium',
    short: 'Dziki dereń, rubinowa cierpkość', desc: 'Jasne piwo spotyka unikalny charakter dzikiego derenia: głębokie, owocowe refleksy i rześki, elegancki profil.',
    legend: 'Odyniec o karmazynowej sierści strzeże derenia na zboczach nad Sanem. Kto zerwie owoc bez pytania, ten długo szuka drogi do domu.', f: [2, 3, 4, 2, 1], temp: '5–7°C', glass: 'tulipan', pair: ['Pasztet z dziczyzny', 'Kaczka', 'Sery dojrzewające'] },
  { slug: 'lesne-widmo', name: 'Leśne Widmo', style: 'Premium · Hazy IPA', abv: '5,7', ext: '16', price: 11.99, color: '#4F6B4A', fam: ['ipa'], series: 'Premium', premium: 'No. 01 · Ryś', hops: 'Galaxy, Nelson Sauvin, Sabro, Citra', badge: 'Premium',
    short: 'Tropiki, brzoskwinia, kokos, jeżyna', desc: 'Naturalnie mętne IPA na czterech chmielach – Galaxy, Nelson Sauvin, Sabro i Citra. Potężny aromat tropików, dojrzałej brzoskwini, kokosa i jeżyn na cytrusowo-żywicznym tle.',
    legend: 'Ryś, którego nikt nie widział, choć każdy czuł jego wzrok. Kto go spotka w mgle nad Sanem, wraca do domu z dziwnym spokojem i zapachem lasu we włosach.', f: [3, 2, 5, 3, 1], temp: '6–8°C', glass: 'teku', pair: ['Pstrąg z grilla', 'Tacos z rybą', 'Kozi ser z miodem'] },
  { slug: 'nocna-warta', name: 'Nocna Warta', style: 'Premium · IPA bezalkoholowe', abv: '0,0', price: 11.99, color: '#2D3B55', fam: ['ipa', 'zero'], series: 'Premium', premium: 'No. 03 · Sowa', hops: 'chmiele nowofalowe', badge: '0,0%',
    short: 'Cytrusy, kokos, tropiki – zero procent', desc: 'Naturalnie mętne i soczyste IPA bezalkoholowe, które już od pierwszego łyka przenosi w tropiki: cytrusy, kokos i owoce tropikalne.',
    legend: 'Sowa czuwa, gdy inni świętują. Pełny chmiel, a rano – czysta głowa i droga przez połoniny.', f: [3, 2, 5, 2, 1], temp: '5–7°C', glass: 'teku', pair: ['Curry', 'Kurczak w cytrusach', 'Na szlak'] },
];

export const MERCH = [
  { slug: 'zestaw-mis-wojtek', name: 'Zestaw Miś Wojtek', price: 39.99, img: 'zestaw-mis-wojtek', note: 'Plaster Miodu, Borówka Zero i szklanka 250 ml', tag: 'Zestaw' },
  { slug: 'nosidelko-drewno-6', name: 'Nosidełko drewniane na 6', price: 38.99, img: 'nosidelko-drewno-6', note: 'Celofan, sznurek i wełna drzewna w komplecie', tag: 'Nosidełko' },
  { slug: 'nosidelko-4', name: 'Nosidełko ze sklejki na 4', price: 28.99, img: 'nosidelko-4', note: 'Grawer z jeleniem', tag: 'Nosidełko' },
  { slug: 'nosidelko-rys-3', name: 'Nosidełko Ryś na 3', price: 5.99, img: 'nosidelko-rys-3', note: 'Karton z motywem rysia i wilka', tag: 'Nosidełko' },
  { slug: 'nosidelko-biesy-3', name: 'Nosidełko Biesy na 3', price: 5.99, img: 'nosidelko-biesy-3', note: 'Karton z nocnym niebem', tag: 'Nosidełko' },
  { slug: 'tshirt-wilk', name: 'T-shirt Bieszczady · Wilk', price: 54.99, img: 'tshirt-wilk', note: '„Wolność ma kierunek”', tag: 'Merch' },
  { slug: 'tshirt-jelen', name: 'T-shirt Bieszczady · Jeleń', price: 54.99, img: 'tshirt-jelen', note: '„Tu jest moje miejsce”', tag: 'Merch' },
  { slug: 'tshirt-rys', name: 'T-shirt Bieszczady · Ryś', price: 54.99, img: 'tshirt-rys', note: '„Tam, gdzie czas płynie wolniej”', tag: 'Merch' },
  { slug: 'kufel', name: 'Kufel Duch Sanu 500 ml', price: 19.99, img: 'kufel', note: 'Grube szkło z nadrukiem', tag: 'Merch' },
  { slug: 'czapka', name: 'Czapka z daszkiem', price: 34.99, img: 'czapka', note: '100% bawełna 340 g/m²', tag: 'Merch' },
];

export const CRATES = [
  { n: 3, name: 'Nosidełko Ryś na 3', price: 5.99, img: 'nosidelko-rys-3', disc: 0 },
  { n: 4, name: 'Nosidełko ze sklejki na 4', price: 28.99, img: 'nosidelko-4', disc: 0 },
  { n: 6, name: 'Nosidełko drewniane na 6', price: 38.99, img: 'nosidelko-drewno-6', disc: 0.05 },
  { n: 12, name: 'Skrzynka 12 (2 × drewno na 6)', price: 77.98, img: 'nosidelko-drewno-6', disc: 0.10 },
];
