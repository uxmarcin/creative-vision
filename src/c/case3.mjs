// Case study v3: wspólny szablon realizacji (styl .gg3), układy rozdziałów: sticky, zig, stack
const P = {
  'house': '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
  'sliders': '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
  'gem': '<path d="M6 3h12l4 6-10 13L2 9Z"/><path d="M11 3 8 9l4 13 4-13-3-6"/><path d="M2 9h20"/>',
  'ruler': '<path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/>',
  'bag': '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
  'smartphone': '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
  'building': '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/>',
  'briefcase': '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
  'layers': '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
  'devices': '<path d="M18 8V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h8"/><path d="M10 19v-3.96 3.15"/><path d="M7 19h5"/><rect width="6" height="10" x="16" y="12" rx="2"/>',
  'store': '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"/>',
  'files': '<path d="M20 7h-3a2 2 0 0 1-2-2V2"/><path d="M9 18a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2Z"/><path d="M3 7.6v12.8A1.6 1.6 0 0 0 4.6 22h9.8"/>',
  'package': '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"/><path d="m7.5 4.27 9 5.15"/>',
  'flag': '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/>',
  'languages': '<path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>',
  'pin': '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
  'card': '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
  'tag': '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
  'wrench': '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
  'user': '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
  'search': '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
  'scale': '<path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>',
  'pen': '<path d="M15.707 21.293a1 1 0 0 1-1.414 0l-1.586-1.586a1 1 0 0 1 0-1.414l5.586-5.586a1 1 0 0 1 1.414 0l1.586 1.586a1 1 0 0 1 0 1.414z"/><path d="m18 13-1.375-6.874a1 1 0 0 0-.746-.776L3.235 2.028a1 1 0 0 0-1.207 1.207L5.35 15.879a1 1 0 0 0 .776.746L13 18"/><path d="m2.3 2.3 7.286 7.286"/><circle cx="11" cy="11" r="2"/>',
  'click': '<path d="M14 4.1 12 6"/><path d="m5.1 8-2.9-.8"/><path d="m6 12-1.9 2"/><path d="M7.2 2.2 8 5.1"/><path d="M9.037 9.69a.498.498 0 0 1 .653-.653l11 4.5a.5.5 0 0 1-.074.949l-4.349 1.041a1 1 0 0 0-.74.739l-1.04 4.35a.5.5 0 0 1-.95.074z"/>',
  'rocket': '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
  'receipt': '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
  'cart': '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
  'filecheck': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>',
  'filetext': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
  'check': '<path d="M20 6 9 17l-5-5"/>',
  'target': '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
  'image': '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
  'key': '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>',
  'question': '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
  'phone': '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
  'grid': '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
  'quote': '<path d="M16 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/><path d="M5 3a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2 1 1 0 0 1 1 1v1a2 2 0 0 1-2 2 1 1 0 0 0-1 1v2a1 1 0 0 0 1 1 6 6 0 0 0 6-6V5a2 2 0 0 0-2-2z"/>',
  'map': '<path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path d="M15 5.764v15"/><path d="M9 3.236v15"/>',
  'calendar': '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
  'ticket': '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>',
  'users': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  'dashboard': '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
  'message': '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
  'shield': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
  'compare': '<circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><path d="M11 18H8a2 2 0 0 1-2-2V9"/>',
  'usercheck': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
  'login': '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
  'route': '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
  'award': '<path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/><circle cx="12" cy="8" r="6"/>',
  'clock': '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
  'buoy': '<circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/><circle cx="12" cy="12" r="4"/>',
  'arrow': '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
  'shapes': '<path d="M8.3 10a.7.7 0 0 1-.626-1.079L11.4 3a.7.7 0 0 1 1.198-.043L16.3 8.9a.7.7 0 0 1-.572 1.1Z"/><rect x="3" y="14" width="7" height="7" rx="1"/><circle cx="17.5" cy="17.5" r="3.5"/>'
};
const ic = (n, cls = 'gg3-ic') => '<svg class="' + cls + '" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (P[n] || P.check) + '</svg>';
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const gr = s => esc(s).replace(/\*([^*]+)\*/g, '<span class="sw-gr">$1</span>');
const FI = { 'Klient': 'building', 'Produkt': 'package', 'Branża': 'briefcase', 'Zakres': 'layers', 'Nasza rola': 'layers', 'Platforma': 'store', 'Podstrony': 'files', 'Ekrany': 'devices', 'Etapy': 'flag', 'Języki': 'languages', 'Miejsce': 'pin', 'Płatności': 'card', 'Oferta': 'tag', 'Usługi': 'wrench', 'Usługa': 'wrench', 'Typ': 'shapes' };

const GG = {
  facts: [['Klient', 'GoldenGun.pl, Bocheński Jewellery'], ['Branża', 'Biżuteria ze złota 585 i srebra 925'], ['Zakres', 'Audyt UX, projekt UX i UI, wdrożenie'], ['Platforma', 'PrestaShop 9']],
  big: 'Kupując biżuterię przez internet, zadajesz sobie trzy pytania: jaki rozmiar wybrać, czy to na pewno złoto 585 i ile zapłacisz na końcu. Nowy GoldenGun odpowiada na każde z nich, zanim klikniesz „Kupuję”.',
  chH: 'Dobry produkt, który gubił klientów *tuż przed zapłatą*',
  chP: ['GoldenGun.pl ma piękną biżuterię, dobre zdjęcia i markę z wieloletnią historią. W sklepie jest około 1 640 produktów. Klienci trafiali do niego głównie z reklam i głównie przez telefon.', 'Problem w tym, że najwięcej osób rezygnowało tam, gdzie już chciały kupić: w koszyku i przy płatności. Naszym zadaniem było odpowiedzieć na pytania klienta, zanim zdąży je zadać.'],
  finds: [['card', 'BLIK schowany, raty zaznaczone domyślnie', 'Przy zamówieniu za 125,90 zł z góry wybrane były raty, a słowo „BLIK” nie padało nigdzie.'], ['receipt', '„Łącznie Netto” przy cenie z VAT', 'Kwota z podatkiem była opisana jako netto, więc klient mógł się bać, że zapłaci więcej.'], ['cart', 'Koszyk na telefonie bez produktów', 'W wersji mobilnej znikały nazwy i zdjęcia, zostawały tylko cena i ilość.'], ['filecheck', 'Brak akceptacji regulaminu sklepu', 'Przy „Kupuję i płacę” nie było regulaminu, a linki w checkboxie prowadziły w złe miejsca.'], ['ruler', 'Rozmiar pierścionka na ślepo', 'Mała lista bez tabeli rozmiarów, a przycisk zakupu dopiero na trzecim ekranie telefonu.'], ['search', 'Wyszukiwarka, która nie znajdowała', 'Po wpisaniu „łańcuszek męski” pokazywał się medalik, a na telefonie nie było lupy.']],
  proc: [['search', 'Audyt UX', 'Cała ścieżka zakupu na komputerze i telefonie, szybkość, SEO i regulamin.'], ['scale', 'Porównanie z rynkiem', 'Zestawienie z największymi jubilerami w Polsce i wytycznymi Baymard.'], ['pen', 'Projekt w Figmie', 'Ponad 40 ekranów na telefon i komputer, od strony głównej po płatność.'], ['click', 'Klikalny prototyp', 'Cały zakup do przejścia, zanim powstanie linijka kodu.'], ['rocket', 'Wdrożenie', 'Sklep na kopii i zamówienie testowe po każdym etapie.']],
  solH: 'Sklep, który odpowiada na pytania, *zanim padną*',
  solP: 'Każdy ekran zaprojektowaliśmy wokół jednej obawy klienta: najpierw pewność co do towaru, potem rozmiaru, na końcu ceny i płatności.',
  ch: [['house', 'Strona główna', 'Od razu wiesz, gdzie jesteś', 'Kolekcja, krótka obietnica marki i przycisk do zakupów. Pod nimi cztery konkrety: wysyłka w 24 godziny, zwroty, certyfikat próby i pakowanie gratis.', '/img/gg4-hero.webp'], ['sliders', 'Listing z filtrami', 'Kategorie zamiast przypadkowych tagów', 'Filtry kruszcu, ceny, rozmiaru i kamienia, z licznikiem i sortowaniem. Kafelek pokazuje próbę złota, wagę i ocenę, a przy promocji najniższą cenę z 30 dni.', '/img/gg4-listing.webp'], ['gem', 'Karta produktu', 'Wszystko, czego potrzebujesz do decyzji', 'Cena z VAT, raty 0%, wybór rozmiaru i przycisk zakupu na pierwszym ekranie. Niżej godzina, do której paczka wyjdzie jeszcze dziś.', '/img/gg4-produkt.webp'], ['ruler', 'Przewodnik po rozmiarach', 'Bez zgadywania', 'Przewodnik otwiera się obok produktu. Wystarczy zmierzyć pierścionek, który już pasuje, a sklep sam zaznaczy rozmiar.', '/img/gg4-rozmiar.webp'], ['bag', 'Koszyk', 'Widzisz, co kupujesz', 'Wysuwany koszyk ze zdjęciem, nazwą i rozmiarem. Kwota z VAT, data dostawy i to, ile brakuje do darmowej wysyłki.', '/img/gg4-koszyk.webp'], ['smartphone', 'Zamówienie z BLIK', 'Jedna strona i gotowe', 'Kontakt, dostawa i płatność na jednej stronie, paczkomat na mapie. BLIK zaznaczony domyślnie, regulamin przy przycisku, kwota opisana wprost.', '/img/gg4-zamowienie.webp']],
  mobP: 'Większość ruchu z reklam przychodzi z telefonów, dlatego każdy ekran projektowaliśmy najpierw w wersji mobilnej. Wyszukiwarka jest zawsze widoczna, przycisk zakupu duży i kontrastowy, a kod BLIK wpisujesz kciukiem, bez przewijania.',
  mob: [['/img/gg4-mobile-hero.webp', 'Strona główna'], ['/img/gg4-mobile-produkt.webp', 'Karta produktu'], ['/img/gg4-mobile-rozmiar.webp', 'Rozmiar'], ['/img/gg4-mobile-dodano.webp', 'Dodano do koszyka'], ['/img/gg4-mobile-zamowienie.webp', 'Zamówienie']]
};

// dodatkowe ekrany z telefonu do płynącego paska
export const MOBX = {"goldengun": ["/img/gg4-m-menu.webp", "/img/gg4-m-szukaj.webp", "/img/gg4-m-listing.webp", "/img/gg4-m-filtry.webp", "/img/gg4-m-koszyk.webp", "/img/gg4-m-paczkomat.webp", "/img/gg4-m-potwierdzenie.webp"], "sztuka-wykonczen": ["/img/ClZgLXkzgRN8EtGimizHz4ciwuQ.webp"], "4clubbers": ["/img/mob-4clubbers-01.webp", "/img/mob-4clubbers-02.webp", "/img/mob-4clubbers-03.webp", "/img/mob-4clubbers-04.webp", "/img/mob-4clubbers-05.webp", "/img/mob-4clubbers-06.webp", "/img/mob-4clubbers-07.webp", "/img/mob-4clubbers-08.webp", "/img/mob-4clubbers-09.webp", "/img/eISy4D5iz2HY6Q3rF08lkaU.webp", "/img/qZ22WeFOd9ymGkMH59lP19BrM.webp"], "bud-mad": ["/img/mob-bud-mad-03.webp", "/img/mob-bud-mad-05.webp"], "tekma": ["/img/mob-tekma-01.webp", "/img/mob-tekma-02.webp", "/img/mob-tekma-03.webp", "/img/mob-tekma-04.webp", "/img/mob-tekma-05.webp", "/img/mob-tekma-06.webp", "/img/mob-tekma-07.webp", "/img/mob-tekma-08.webp", "/img/mob-tekma-09.webp", "/img/tekma-mobile-pulpit.webp", "/img/tekma-mobile-kandydat.webp", "/img/tekma-mobile-check-in.webp"], "jctp-academy": ["/img/jctp-mobile-hero.webp", "/img/jctp-mobile-szkolenia.webp", "/img/jctp-mobile-terminy.webp", "/img/jctp-mobile-salony.webp"], "bzimny": ["/img/mob-bzimny-01.webp", "/img/mob-bzimny-02.webp", "/img/mob-bzimny-03.webp", "/img/mob-bzimny-04.webp", "/img/mob-bzimny-05.webp", "/img/mob-bzimny-06.webp", "/img/mob-bzimny-07.webp", "/img/mob-bzimny-08.webp", "/img/mob-bzimny-09.webp", "/img/bzimny-mobile-pakiety.webp", "/img/bzimny-mobile-od-zakupu.webp"]};

// układ rozdziałów, ikony rozdziałów, nagłówek z wyróżnieniem, kolejność tła
export const CS3 = {
  'goldengun': { lay: 'sticky', h: 'GoldenGun.pl: sklep z biżuterią, w którym rozmiar i płatność *przestają być problemem.*', k: 'Case study · E-commerce · Biżuteria', lead: 'Zaczęliśmy od audytu UX, który pokazał, gdzie sklep traci klientów. Potem zaprojektowaliśmy go od nowa: ponad 40 ekranów na telefon i komputer, od strony głównej po płatność BLIK-iem.' },
  'sztuka-wykonczen': { lay: 'zig', h: 'Sztuka Wykończeń: strona firmy wykończeniowej, która *prowadzi do wyceny*', ic: ['image', 'grid', 'key', 'question'], chH: 'Klient wybiera wykonawcę *po zdjęciach*', solH: 'Najpierw gotowe wnętrza, *potem oferta*', mobH: 'Kciukiem, *bez powiększania*' },
  '4clubbers': { lay: 'sticky', h: '4clubbers: aplikacja dla fanów muzyki klubowej, od katalogu wydarzeń *po społeczność*', ic: ['house', 'map', 'ticket', 'users', 'dashboard', 'message', 'shield'], chH: 'Na festiwal jedzie się *z ekipą*', solH: 'Od katalogu wydarzeń *do społeczności*', mobH: 'Aplikacja, która mieści się *w kieszeni*', dark: 1 },
  'bud-mad': { lay: 'stack', h: 'Bud-Mad: strona firmy remontowo-budowlanej, *z której się dzwoni*', ic: ['phone', 'grid', 'image', 'quote'], chH: 'Wykonawcę wybiera się *po zaufaniu*', solH: 'Oferta, realizacje i opinie *na jednej stronie*', mobH: 'Telefon do firmy *jednym kliknięciem*' },
  'tekma': { lay: 'zig', h: 'Tekma.app: strona aplikacji do *rekrutacji i wdrażania* pracowników', ic: ['devices', 'click', 'route', 'compare', 'usercheck', 'login'], chH: 'Aplikacji HR nie wybiera się *po jednym zdaniu*', solH: 'Produkt pokazany *w działaniu*', mobH: 'Wygodnie także *na telefonie*' },
  'jctp-academy': { lay: 'stack', h: 'JCTP Academy: strona szkoleń barberskich *z zapisem online*', ic: ['quote', 'route', 'target', 'calendar', 'pin', 'sliders'], chH: 'Szkolenie to decyzja, *nie impuls*', solH: 'Od pierwszego kliknięcia *do zapisu*', mobH: 'Zapis wygodny *na telefonie*' },
  'bzimny': { lay: 'sticky', h: 'bzimny.com: strona trenera personalnego *z płatnością online*', ic: ['user', 'layers', 'tag', 'award', 'card', 'sliders'], chH: 'Mniej pytań o cenę, *więcej treningów*', solH: 'Od pierwszego ekranu *do płatności*', mobH: 'Pakiet kupisz *w kilka kliknięć*' },
  'uxaudyt': { lay: 'zig', h: 'uxaudyt.pl: nasza usługa audytów UX *z płatnością online*', ic: ['tag', 'filetext', 'clock', 'buoy'], chH: 'Usługę ekspercką też można *kupić sama*', solH: 'Wszystko, czego potrzeba *przed zakupem*', mobH: 'Zamówienie wygodne *na telefonie*', dark: 1 }
};

const kick = t => '<p class="sw-k">' + esc(t) + '</p>';
const img = (src, alt, cls = '', lazy = 1) => '<img' + (cls ? ' class="' + cls + '"' : '') + ' src="' + src + '" alt="' + esc(alt) + '"' + (lazy ? ' loading="lazy"' : '') + ' decoding="async">';

function media(s, alt) {
  if (s.phones) return '<div class="gg3-phs">' + s.phones.map(p => img(p, alt)).join('') + '</div>';
  return '<div class="gg3-md' + (s.phone ? ' has-ph' : '') + '">' + img(s.img, alt, 'd') + (s.phone ? img(s.phone, alt + ' na telefonie', 'm') : '') + '</div>';
}

export function cs3(t, e, nxt) {
  const C = CS3[t], gg = t === 'goldengun', slug = t;
  const art = '/img/case-' + t + '.webp';
  let h = '';
  // zrzuty pod hero
  h += '<section class="gg3-shots-s"><div class="sw-w"><div class="gg3-shots">' + img(gg ? '/img/gg4-hero.webp' : e.d, e.n + ' na komputerze', 'd', 0) + (e.m ? img(gg ? '/img/gg4-mobile-hero.webp' : e.m, e.n + ' na telefonie', 'm', 0) : '') + '</div></div></section>';
  // metryczka
  const facts = gg ? GG.facts : (e.facts || []);
  const big = gg ? GG.big : e.got;
  h += '<section class="gg3-facts"><div class="sw-w gg3-2c"><dl>' + facts.map(([a, b]) => '<div>' + ic(FI[a] || 'check', 'gg3-fi') + '<dt>' + esc(a) + '</dt><dd>' + esc(b) + '</dd></div>').join('') + '</dl><p class="gg3-big">' + esc(big) + '</p></div></section>';
  // wyzwanie z ilustracją
  const chP = gg ? GG.chP : [e.ch || (e.goal || [])[1]];
  h += '<section class="gg3-sec gg3-dark gg3-chs"><div class="sw-w gg3-2c gg3-mid"><div>' + kick('Wyzwanie') + '<h2>' + gr(gg ? GG.chH : C.chH) + '</h2><div class="gg3-txt">' + chP.filter(Boolean).map(p => '<p>' + esc(p) + '</p>').join('') + '</div></div><figure class="gg3-art">' + img(art, '') + '</figure></div>';
  if (!gg && e.chl) h += '<div class="sw-w"><ul class="gg3-goals">' + e.chl.map(g => '<li>' + ic('target') + '<span>' + esc(g) + '</span></li>').join('') + '</ul></div>';
  h += '</section>';
  // audyt i proces (GoldenGun)
  if (gg) {
    h += '<section class="gg3-sec gg3-be"><div class="sw-w">' + kick('Audyt UX') + '<div class="gg3-nums"><div><b data-count="52">52</b><span>ustalenia w audycie UX</span></div><div><b class="sw-gr" data-count="7">7</b><span>krytycznych</span></div></div><ul class="gg3-find">' + GG.finds.map(([i, a, b]) => '<li>' + ic(i) + '<div><h3>' + esc(a) + '</h3><p>' + esc(b) + '</p></div></li>').join('') + '</ul></div></section>';
    h += '<section class="gg3-sec"><div class="sw-w">' + kick('Proces') + '<h2>Od audytu do zamówienia testowego</h2><div class="gg3-proc"><div class="gg3-axis" aria-hidden="true"><i></i></div><ol>' + GG.proc.map(([i, a, b]) => '<li>' + ic(i) + '<h3>' + esc(a) + '</h3><p>' + esc(b) + '</p></li>').join('') + '</ol></div></div></section>';
  }
  // rozwiązanie
  const ch = gg ? GG.ch.map(([i, a, b, p, s]) => ({ i, h: a, sub: b, p: [p], img: s })) : (e.story || []).map((s, k) => ({ i: (C.ic || [])[k], h: s.h, p: s.p, img: s.img, phone: s.phone, phones: s.phones }));
  const solH = gg ? GG.solH : (e.vh || C.solH);
  const dk = C.dark ? ' gg3-dark' : '';
  h += '<section class="gg3-sec gg3-sol' + dk + '"><div class="sw-w"><div class="gg3-2c gg3-solhd"><div>' + kick('Rozwiązanie') + '<h2>' + gr(solH) + '</h2></div>' + (gg ? '<p class="gg3-txt">' + esc(GG.solP) + '</p>' : '') + '</div><div class="gg3-' + C.lay + '">';
  h += ch.filter(s => s.img || s.phones).map(s => {
    const t = '<div class="t">' + ic(s.i) + '<h3>' + esc(s.h) + '</h3>' + (s.sub ? '<h4>' + esc(s.sub) + '</h4>' : '') + s.p.map(p => '<p>' + esc(p) + '</p>').join('') + '</div>';
    if (gg) return '<article>' + t + img(s.img, s.h) + '</article>';
    if (s.img === e.d && !s.phones) { const ph = [...new Set([s.phone, e.m, ...(MOBX[slug] || [])].filter(Boolean))].slice(0, 3); const sm = { phones: ph }; return '<article>' + (C.lay === 'stack' ? media(sm, s.h) + t : t + media(sm, s.h)) + '</article>'; }
    return '<article>' + (C.lay === 'stack' ? media(s, s.h) + t : t + media(s, s.h)) + '</article>';
  }).join('');
  // rozdziały bez zrzutu (np. panel) jako karta tekstowa
  const noimg = ch.filter(s => !s.img && !s.phones);
  if (noimg.length) h += '<div class="gg3-note">' + noimg.map(s => '<div>' + ic(s.i) + '<h3>' + esc(s.h) + '</h3>' + s.p.map(p => '<p>' + esc(p) + '</p>').join('') + '</div>').join('') + '</div>';
  h += '</div></div></section>';
  // telefon
  let mob = gg ? GG.mob : [];
  if (!gg) {
    const seen = new Set();
    (e.story || []).forEach(s => { [s.phone, ...(s.phones || [])].filter(Boolean).forEach(p => { if (!seen.has(p)) { seen.add(p); mob.push([p, '']); } }); });
    if (e.m && !seen.has(e.m)) mob.unshift([e.m, '']);
    if (e.screens) e.screens.forEach(([p, n]) => { if (!seen.has(p)) { seen.add(p); mob.push([p, n]); } });
  }
  (MOBX[t] || []).forEach(p => { if (!mob.some(m => m[0] === p)) mob.push([p, '']); });
  if (mob.length >= 3 && (gg || C.mobH)) {
    const fig = (aria) => mob.map(([p, n]) => '<figure' + (aria ? ' aria-hidden="true"' : '') + '>' + img(p, aria ? '' : (n || e.n + ' na telefonie')) + (n ? '<figcaption>' + esc(n) + '</figcaption>' : '') + '</figure>').join('');
    h += '<section class="gg3-sec gg3-mobs' + (C.dark ? ' gg3-be' : ' gg3-dark') + '"><div class="sw-w"><div class="gg3-2c gg3-solhd"><div>' + kick('Telefon') + '<h2>' + gr(gg ? 'Projektowany najpierw *na telefon*' : C.mobH) + '</h2></div>' + (gg ? '<p class="gg3-txt">' + esc(GG.mobP) + '</p>' : '') + '</div></div><div class="gg3-mq" style="--n:' + mob.length * Math.ceil(10 / mob.length) + '"><div class="gg3-mq-t">' + fig(0) + fig(1).repeat(Math.ceil(10 / mob.length) * 2 - 1) + '</div></div></section>';
  }
  // zachęta po sekcji telefonu
  const what = { 'goldengun': 'swój sklep internetowy', '4clubbers': 'swoją aplikację', 'tekma': 'stronę swojego produktu', 'uxaudyt': 'swój sklep albo stronę' }[t] || 'swoją firmę i stronę, której potrzebujesz';
  h += '<section class="gg3-mcta"><div class="sw-w"><div class="gg3-mcta-in">' + ic('message') + '<p><b>Opisz ' + esc(what) + '.</b> Wycenę otrzymasz tego samego dnia.</p><a class="sw-ob" href="/kontakt">Opisz projekt</a></div></div></section>';
  // co powstało
  if (!gg && e.gotl) h += '<section class="gg3-sec gg3-be"><div class="sw-w gg3-2c"><div>' + kick(e.gotk || 'Co powstało') + '<h2>' + gr(t === 'uxaudyt' ? 'Co działa *dziś*' : 'Co dostał *klient*') + '</h2></div><ul class="gg3-got">' + e.gotl.map(g => '<li>' + ic('check') + '<span>' + esc(g) + '</span></li>').join('') + '</ul></div></section>';
  // następny projekt i CTA
  h += '<section class="gg3-next gg3-dark"><div class="sw-w">' + kick('Następny projekt') + '<a class="gg3-nx" href="/realizacje/' + nxt[0] + '">' + esc(nxt[1]) + ' <span aria-hidden="true">→</span></a><div class="gg3-cta"><h2>' + (t === 'uxaudyt' || t === '4clubbers' || t === 'tekma' ? 'Masz pomysł na *produkt*? Porozmawiajmy' : (t === 'goldengun' ? 'Twój sklep też może sprzedawać *łatwiej*' : 'Twoja strona też może *zbierać zapytania*')).replace(/\*([^*]+)\*/g, '<span class="sw-gr">$1</span>') + '</h2><div class="gg3-cta-r"><a class="sw-ob sw-ob-w" href="/kontakt">Zamów bezpłatną wycenę</a><p>Odpiszemy w ciągu 1 dnia roboczego, pn–pt 9:00–17:00.</p></div></div></div></section>';
  return h;
}

// indeks realizacji: duże sceny z ekranami, układ pełna szerokość / para
export function rl3(V, B) {
  const shot = k => k === 'goldengun' ? ['/img/gg4-hero.webp', '/img/gg4-mobile-hero.webp'] : [B[k].d, B[k].m];
  const card = (k, big) => {
    const e = B[k], [d, m] = shot(k);
    return '<a class="rl3-c' + (big ? ' big' : '') + '" href="/realizacje/' + k + '"><div class="rl3-st"><div class="rl3-sc">' + img(d, 'Realizacja ' + e.n, 'd', big ? 0 : 1) + (m ? img(m, e.n + ' na telefonie', 'm') : '') + '</div></div><div class="rl3-m"><div><h2>' + esc(e.n) + '</h2><p>' + esc(e.k) + '</p></div><span class="rl3-go" aria-hidden="true">' + ic('arrow') + '</span></div></a>';
  };
  let h = '<section class="rl3"><div class="sw-w">', i = 0, row = 0;
  while (i < V.length) {
    if (row % 2 === 0) { h += card(V[i], 1); i++; }
    else { h += '<div class="rl3-pair">' + V.slice(i, i + 2).map(k => card(k, 0)).join('') + '</div>'; i += 2; }
    row++;
  }
  return h + '</div></section>';
}

// oferta na stronie głównej (układ jak webmetric.com, styl Creative Vision)
const OF = [
  ['Sklepy internetowe', [
    ['Sklep internetowy', '/uslugi/sklepy-internetowe', 'art-jak-zalozyc-sklep-internetowy', 'Chcesz sklepu, w którym klienci kupują bez wahania?', 'Projektujemy drogę klienta od wyszukiwarki po płatność BLIK-iem. Zanim powstanie kod, klikasz gotowy prototyp. Od 5 990 zł netto.'],
    ['Sklep na PrestaShop', '/uslugi/sklepy-prestashop', 'art-co-to-jest-prestashop', 'Szukasz platformy bez miesięcznego abonamentu?', 'Jako PrestaShop Expert wdrażamy sklep na platformie, która należy do Ciebie. Bez abonamentu i bez niespodzianek.'],
    ['Migracja sklepu', '/uslugi/sklepy-internetowe', 'art-jaka-platforma-sklepu-internetowego', 'Przenosisz sklep z innej platformy?', 'Przenosimy produkty, klientów i treści, a przekierowania 301 chronią ruch z Google.'],
    ['Płatności i integracje', '/uslugi/sklepy-internetowe', 'art-ile-kosztuje-sklep-internetowy', 'Chcesz, żeby zamówienie zajmowało minutę?', 'BLIK, karty, Apple Pay, InPost i import produktów. Klient płaci, jak lubi, a Ty nie przepisujesz zamówień.']]],
  ['Strony internetowe', [
    ['Strona firmowa', '/uslugi/strony-firmowe', 'art-strona-firmowa-ktora-zbiera-zapytania', 'Strona ładnie wygląda, ale nie przynosi zapytań?', 'W kilka sekund mówi, co robisz i dlaczego warto wybrać Ciebie, a potem prowadzi do telefonu lub formularza. Od 4 900 zł netto.'],
    ['Strony dla firm budowlanych', '/uslugi/strony-dla-firm-budowlanych', 'art-jak-stworzyc-strone-internetowa', 'Prowadzisz firmę budowlaną lub remontową?', 'Twoje realizacje na pierwszym planie, a przycisk „Zadzwoń” zawsze pod kciukiem.'],
    ['Strony internetowe Rzeszów', '/strony-internetowe-rzeszow', 'art-ile-kosztuje-strona-internetowa', 'Szukasz studia w Rzeszowie?', 'Spotkamy się przy ul. Pańskiej 13A. Z firmami z całej Polski pracujemy zdalnie.']]],
  ['UX/UI Design', [
    ['Audyt UX', '/uslugi/audyt-ux', 'art-ux-karty-produktu', 'Masz ruch, ale sprzedaży jest za mało?', 'Sprawdzamy sklep oczami klienta i dajemy listę poprawek od tych, które dadzą najwięcej. Od 1 900 zł netto.'],
    ['Projektowanie UX/UI', '/wiedza/projektowanie-ux-ui', 'art-projektowanie-ux-ui', 'Chcesz zobaczyć projekt, zanim zapłacisz za kod?', 'Ekrany w Figmie i klikalny prototyp. Testujesz go jak gotowy produkt, zanim powstanie kod.'],
    ['Aplikacje mobilne', '/uslugi/aplikacje-mobilne', 'art-ile-kosztuje-aplikacja-mobilna', 'Masz pomysł na aplikację?', 'Od warsztatu i prototypu po pierwszą wersję na iOS i Android.']]],
  ['Opieka i rozwój', [
    ['Opieka nad stroną i sklepem', '/uslugi/opieka', 'art-moduly-prestashop', 'Nie chcesz martwić się aktualizacjami?', 'Aktualizacje, kopie zapasowe i nowe funkcje, kiedy ich potrzebujesz. Od 490 zł netto miesięcznie.'],
    ['SEO techniczne', '/uslugi/opieka', 'art-sklep-a-zakupy-przez-asystentow-ai', 'Chcesz, żeby Google i asystenci AI rozumieli Twoją ofertę?', 'Szybsze ładowanie, porządne adresy i dane strukturalne. Oferta czytelna dla Google i ChatGPT.'],
    ['Agencja e-commerce', '/agencja-ecommerce', 'art-jak-wybrac-agencje-e-commerce', 'Potrzebujesz partnera na dłużej?', 'Co miesiąc planujemy, mierzymy i wdrażamy kolejne poprawki w Twoim sklepie.']]]
];
export function of3() {
  let h = '<div class="sw-w of3"><p class="sw-k">Oferta</p><h2 class="of3-h">Chcesz, żeby strona i sklep sprzedawały więcej? <span class="sw-gr">Pomożemy Ci w tym.</span></h2>';
  OF.forEach(([cat, items], ci) => {
    h += '<div class="of3-row" data-of><div class="of3-l"><span class="of3-n">0' + (ci + 1) + '</span><h3>' + esc(cat) + '</h3><div class="of3-tabs" role="tablist" aria-label="' + esc(cat) + '">' +
      items.map((it, i) => '<button type="button" role="tab" aria-selected="' + (i ? 'false' : 'true') + '" class="of3-t' + (i ? '' : ' on') + '" data-i="' + i + '"><span>' + esc(it[0]) + '</span>' + ic('arrow', 'of3-a') + '</button>').join('') + '</div></div>' +
      '<div class="of3-m">' + items.map((it, i) => '<div class="of3-p' + (i ? '' : ' on') + '" role="tabpanel" data-i="' + i + '"' + (i ? ' hidden' : '') + '><h4>' + esc(it[3]) + '</h4><p>' + esc(it[4]) + '</p><a class="sw-tl" href="' + it[1] + '">Zobacz więcej</a></div>').join('') + '</div>' +
      '<div class="of3-im">' + items.map((it, i) => '<img class="' + (i ? '' : 'on') + '" data-i="' + i + '" src="/img/' + it[2] + '.webp" alt="" loading="lazy" decoding="async">').join('') + '</div></div>';
  });
  return h + '</div>';
}
