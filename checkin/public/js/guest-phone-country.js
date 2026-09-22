(function() {
    var guestPhoneCountryButton = document.getElementById("guest-phone-country-button");
    var guestPhoneCountryCode = document.getElementById("guest-phone-country-code");
    var guestPhoneCountryLabel = document.getElementById("guest-phone-country-label");
    var guestPhoneCountryMenu = document.getElementById("guest-phone-country-menu");
    var guestPhoneCountryList = document.getElementById("guest-phone-country-list");
    var guestPhoneCountrySearch = document.getElementById("guest-phone-country-search");
    var guestPhoneInputs = document.querySelectorAll('input[name="phone"]');

    var flagUrl = function(iso2) {
        if (!iso2) return '';
        return 'https://flagcdn.com/w40/' + iso2.toLowerCase() + '.png';
    };

    // Self-contained country list (from libphonenumber-js metadata).
    // No external fetch, no CORS dependency.
    var guestPhoneCountryData = [
    {
        "iso2": "AF",
        "code": "+93",
        "name": "Afghanistan"
    },
    {
        "iso2": "AX",
        "code": "+358",
        "name": "\u00c5land Islands"
    },
    {
        "iso2": "AL",
        "code": "+355",
        "name": "Albania"
    },
    {
        "iso2": "DZ",
        "code": "+213",
        "name": "Algeria"
    },
    {
        "iso2": "AS",
        "code": "+1",
        "name": "American Samoa"
    },
    {
        "iso2": "AD",
        "code": "+376",
        "name": "Andorra"
    },
    {
        "iso2": "AO",
        "code": "+244",
        "name": "Angola"
    },
    {
        "iso2": "AI",
        "code": "+1",
        "name": "Anguilla"
    },
    {
        "iso2": "AG",
        "code": "+1",
        "name": "Antigua & Barbuda"
    },
    {
        "iso2": "AR",
        "code": "+54",
        "name": "Argentina"
    },
    {
        "iso2": "AM",
        "code": "+374",
        "name": "Armenia"
    },
    {
        "iso2": "AW",
        "code": "+297",
        "name": "Aruba"
    },
    {
        "iso2": "AC",
        "code": "+247",
        "name": "Ascension Island"
    },
    {
        "iso2": "AU",
        "code": "+61",
        "name": "Australia"
    },
    {
        "iso2": "AT",
        "code": "+43",
        "name": "Austria"
    },
    {
        "iso2": "AZ",
        "code": "+994",
        "name": "Azerbaijan"
    },
    {
        "iso2": "BS",
        "code": "+1",
        "name": "Bahamas"
    },
    {
        "iso2": "BH",
        "code": "+973",
        "name": "Bahrain"
    },
    {
        "iso2": "BD",
        "code": "+880",
        "name": "Bangladesh"
    },
    {
        "iso2": "BB",
        "code": "+1",
        "name": "Barbados"
    },
    {
        "iso2": "BY",
        "code": "+375",
        "name": "Belarus"
    },
    {
        "iso2": "BE",
        "code": "+32",
        "name": "Belgium"
    },
    {
        "iso2": "BZ",
        "code": "+501",
        "name": "Belize"
    },
    {
        "iso2": "BJ",
        "code": "+229",
        "name": "Benin"
    },
    {
        "iso2": "BM",
        "code": "+1",
        "name": "Bermuda"
    },
    {
        "iso2": "BT",
        "code": "+975",
        "name": "Bhutan"
    },
    {
        "iso2": "BO",
        "code": "+591",
        "name": "Bolivia"
    },
    {
        "iso2": "BA",
        "code": "+387",
        "name": "Bosnia & Herzegovina"
    },
    {
        "iso2": "BW",
        "code": "+267",
        "name": "Botswana"
    },
    {
        "iso2": "BR",
        "code": "+55",
        "name": "Brazil"
    },
    {
        "iso2": "IO",
        "code": "+246",
        "name": "British Indian Ocean Territory"
    },
    {
        "iso2": "VG",
        "code": "+1",
        "name": "British Virgin Islands"
    },
    {
        "iso2": "BN",
        "code": "+673",
        "name": "Brunei"
    },
    {
        "iso2": "BG",
        "code": "+359",
        "name": "Bulgaria"
    },
    {
        "iso2": "BF",
        "code": "+226",
        "name": "Burkina Faso"
    },
    {
        "iso2": "BI",
        "code": "+257",
        "name": "Burundi"
    },
    {
        "iso2": "KH",
        "code": "+855",
        "name": "Cambodia"
    },
    {
        "iso2": "CM",
        "code": "+237",
        "name": "Cameroon"
    },
    {
        "iso2": "CA",
        "code": "+1",
        "name": "Canada"
    },
    {
        "iso2": "CV",
        "code": "+238",
        "name": "Cape Verde"
    },
    {
        "iso2": "BQ",
        "code": "+599",
        "name": "Caribbean Netherlands"
    },
    {
        "iso2": "KY",
        "code": "+1",
        "name": "Cayman Islands"
    },
    {
        "iso2": "CF",
        "code": "+236",
        "name": "Central African Republic"
    },
    {
        "iso2": "TD",
        "code": "+235",
        "name": "Chad"
    },
    {
        "iso2": "CL",
        "code": "+56",
        "name": "Chile"
    },
    {
        "iso2": "CN",
        "code": "+86",
        "name": "China"
    },
    {
        "iso2": "CX",
        "code": "+61",
        "name": "Christmas Island"
    },
    {
        "iso2": "CC",
        "code": "+61",
        "name": "Cocos (Keeling) Islands"
    },
    {
        "iso2": "CO",
        "code": "+57",
        "name": "Colombia"
    },
    {
        "iso2": "KM",
        "code": "+269",
        "name": "Comoros"
    },
    {
        "iso2": "CG",
        "code": "+242",
        "name": "Congo - Brazzaville"
    },
    {
        "iso2": "CD",
        "code": "+243",
        "name": "Congo - Kinshasa"
    },
    {
        "iso2": "CK",
        "code": "+682",
        "name": "Cook Islands"
    },
    {
        "iso2": "CR",
        "code": "+506",
        "name": "Costa Rica"
    },
    {
        "iso2": "CI",
        "code": "+225",
        "name": "C\u00f4te d\u2019Ivoire"
    },
    {
        "iso2": "HR",
        "code": "+385",
        "name": "Croatia"
    },
    {
        "iso2": "CU",
        "code": "+53",
        "name": "Cuba"
    },
    {
        "iso2": "CW",
        "code": "+599",
        "name": "Cura\u00e7ao"
    },
    {
        "iso2": "CY",
        "code": "+357",
        "name": "Cyprus"
    },
    {
        "iso2": "CZ",
        "code": "+420",
        "name": "Czechia"
    },
    {
        "iso2": "DK",
        "code": "+45",
        "name": "Denmark"
    },
    {
        "iso2": "DJ",
        "code": "+253",
        "name": "Djibouti"
    },
    {
        "iso2": "DM",
        "code": "+1",
        "name": "Dominica"
    },
    {
        "iso2": "DO",
        "code": "+1",
        "name": "Dominican Republic"
    },
    {
        "iso2": "EC",
        "code": "+593",
        "name": "Ecuador"
    },
    {
        "iso2": "EG",
        "code": "+20",
        "name": "Egypt"
    },
    {
        "iso2": "SV",
        "code": "+503",
        "name": "El Salvador"
    },
    {
        "iso2": "GQ",
        "code": "+240",
        "name": "Equatorial Guinea"
    },
    {
        "iso2": "ER",
        "code": "+291",
        "name": "Eritrea"
    },
    {
        "iso2": "EE",
        "code": "+372",
        "name": "Estonia"
    },
    {
        "iso2": "SZ",
        "code": "+268",
        "name": "Eswatini"
    },
    {
        "iso2": "ET",
        "code": "+251",
        "name": "Ethiopia"
    },
    {
        "iso2": "FK",
        "code": "+500",
        "name": "Falkland Islands"
    },
    {
        "iso2": "FO",
        "code": "+298",
        "name": "Faroe Islands"
    },
    {
        "iso2": "FJ",
        "code": "+679",
        "name": "Fiji"
    },
    {
        "iso2": "FI",
        "code": "+358",
        "name": "Finland"
    },
    {
        "iso2": "FR",
        "code": "+33",
        "name": "France"
    },
    {
        "iso2": "GF",
        "code": "+594",
        "name": "French Guiana"
    },
    {
        "iso2": "PF",
        "code": "+689",
        "name": "French Polynesia"
    },
    {
        "iso2": "GA",
        "code": "+241",
        "name": "Gabon"
    },
    {
        "iso2": "GM",
        "code": "+220",
        "name": "Gambia"
    },
    {
        "iso2": "GE",
        "code": "+995",
        "name": "Georgia"
    },
    {
        "iso2": "DE",
        "code": "+49",
        "name": "Germany"
    },
    {
        "iso2": "GH",
        "code": "+233",
        "name": "Ghana"
    },
    {
        "iso2": "GI",
        "code": "+350",
        "name": "Gibraltar"
    },
    {
        "iso2": "GR",
        "code": "+30",
        "name": "Greece"
    },
    {
        "iso2": "GL",
        "code": "+299",
        "name": "Greenland"
    },
    {
        "iso2": "GD",
        "code": "+1",
        "name": "Grenada"
    },
    {
        "iso2": "GP",
        "code": "+590",
        "name": "Guadeloupe"
    },
    {
        "iso2": "GU",
        "code": "+1",
        "name": "Guam"
    },
    {
        "iso2": "GT",
        "code": "+502",
        "name": "Guatemala"
    },
    {
        "iso2": "GG",
        "code": "+44",
        "name": "Guernsey"
    },
    {
        "iso2": "GN",
        "code": "+224",
        "name": "Guinea"
    },
    {
        "iso2": "GW",
        "code": "+245",
        "name": "Guinea-Bissau"
    },
    {
        "iso2": "GY",
        "code": "+592",
        "name": "Guyana"
    },
    {
        "iso2": "HT",
        "code": "+509",
        "name": "Haiti"
    },
    {
        "iso2": "HN",
        "code": "+504",
        "name": "Honduras"
    },
    {
        "iso2": "HK",
        "code": "+852",
        "name": "Hong Kong SAR China"
    },
    {
        "iso2": "HU",
        "code": "+36",
        "name": "Hungary"
    },
    {
        "iso2": "IS",
        "code": "+354",
        "name": "Iceland"
    },
    {
        "iso2": "IN",
        "code": "+91",
        "name": "India"
    },
    {
        "iso2": "ID",
        "code": "+62",
        "name": "Indonesia"
    },
    {
        "iso2": "IR",
        "code": "+98",
        "name": "Iran"
    },
    {
        "iso2": "IQ",
        "code": "+964",
        "name": "Iraq"
    },
    {
        "iso2": "IE",
        "code": "+353",
        "name": "Ireland"
    },
    {
        "iso2": "IM",
        "code": "+44",
        "name": "Isle of Man"
    },
    {
        "iso2": "IL",
        "code": "+972",
        "name": "Israel"
    },
    {
        "iso2": "IT",
        "code": "+39",
        "name": "Italy"
    },
    {
        "iso2": "JM",
        "code": "+1",
        "name": "Jamaica"
    },
    {
        "iso2": "JP",
        "code": "+81",
        "name": "Japan"
    },
    {
        "iso2": "JE",
        "code": "+44",
        "name": "Jersey"
    },
    {
        "iso2": "JO",
        "code": "+962",
        "name": "Jordan"
    },
    {
        "iso2": "KZ",
        "code": "+7",
        "name": "Kazakhstan"
    },
    {
        "iso2": "KE",
        "code": "+254",
        "name": "Kenya"
    },
    {
        "iso2": "KI",
        "code": "+686",
        "name": "Kiribati"
    },
    {
        "iso2": "XK",
        "code": "+383",
        "name": "Kosovo"
    },
    {
        "iso2": "KW",
        "code": "+965",
        "name": "Kuwait"
    },
    {
        "iso2": "KG",
        "code": "+996",
        "name": "Kyrgyzstan"
    },
    {
        "iso2": "LA",
        "code": "+856",
        "name": "Laos"
    },
    {
        "iso2": "LV",
        "code": "+371",
        "name": "Latvia"
    },
    {
        "iso2": "LB",
        "code": "+961",
        "name": "Lebanon"
    },
    {
        "iso2": "LS",
        "code": "+266",
        "name": "Lesotho"
    },
    {
        "iso2": "LR",
        "code": "+231",
        "name": "Liberia"
    },
    {
        "iso2": "LY",
        "code": "+218",
        "name": "Libya"
    },
    {
        "iso2": "LI",
        "code": "+423",
        "name": "Liechtenstein"
    },
    {
        "iso2": "LT",
        "code": "+370",
        "name": "Lithuania"
    },
    {
        "iso2": "LU",
        "code": "+352",
        "name": "Luxembourg"
    },
    {
        "iso2": "MO",
        "code": "+853",
        "name": "Macao SAR China"
    },
    {
        "iso2": "MG",
        "code": "+261",
        "name": "Madagascar"
    },
    {
        "iso2": "MW",
        "code": "+265",
        "name": "Malawi"
    },
    {
        "iso2": "MY",
        "code": "+60",
        "name": "Malaysia"
    },
    {
        "iso2": "MV",
        "code": "+960",
        "name": "Maldives"
    },
    {
        "iso2": "ML",
        "code": "+223",
        "name": "Mali"
    },
    {
        "iso2": "MT",
        "code": "+356",
        "name": "Malta"
    },
    {
        "iso2": "MH",
        "code": "+692",
        "name": "Marshall Islands"
    },
    {
        "iso2": "MQ",
        "code": "+596",
        "name": "Martinique"
    },
    {
        "iso2": "MR",
        "code": "+222",
        "name": "Mauritania"
    },
    {
        "iso2": "MU",
        "code": "+230",
        "name": "Mauritius"
    },
    {
        "iso2": "YT",
        "code": "+262",
        "name": "Mayotte"
    },
    {
        "iso2": "MX",
        "code": "+52",
        "name": "Mexico"
    },
    {
        "iso2": "FM",
        "code": "+691",
        "name": "Micronesia"
    },
    {
        "iso2": "MD",
        "code": "+373",
        "name": "Moldova"
    },
    {
        "iso2": "MC",
        "code": "+377",
        "name": "Monaco"
    },
    {
        "iso2": "MN",
        "code": "+976",
        "name": "Mongolia"
    },
    {
        "iso2": "ME",
        "code": "+382",
        "name": "Montenegro"
    },
    {
        "iso2": "MS",
        "code": "+1",
        "name": "Montserrat"
    },
    {
        "iso2": "MA",
        "code": "+212",
        "name": "Morocco"
    },
    {
        "iso2": "MZ",
        "code": "+258",
        "name": "Mozambique"
    },
    {
        "iso2": "MM",
        "code": "+95",
        "name": "Myanmar (Burma)"
    },
    {
        "iso2": "NA",
        "code": "+264",
        "name": "Namibia"
    },
    {
        "iso2": "NR",
        "code": "+674",
        "name": "Nauru"
    },
    {
        "iso2": "NP",
        "code": "+977",
        "name": "Nepal"
    },
    {
        "iso2": "NL",
        "code": "+31",
        "name": "Netherlands"
    },
    {
        "iso2": "NC",
        "code": "+687",
        "name": "New Caledonia"
    },
    {
        "iso2": "NZ",
        "code": "+64",
        "name": "New Zealand"
    },
    {
        "iso2": "NI",
        "code": "+505",
        "name": "Nicaragua"
    },
    {
        "iso2": "NE",
        "code": "+227",
        "name": "Niger"
    },
    {
        "iso2": "NG",
        "code": "+234",
        "name": "Nigeria"
    },
    {
        "iso2": "NU",
        "code": "+683",
        "name": "Niue"
    },
    {
        "iso2": "NF",
        "code": "+672",
        "name": "Norfolk Island"
    },
    {
        "iso2": "KP",
        "code": "+850",
        "name": "North Korea"
    },
    {
        "iso2": "MK",
        "code": "+389",
        "name": "North Macedonia"
    },
    {
        "iso2": "MP",
        "code": "+1",
        "name": "Northern Mariana Islands"
    },
    {
        "iso2": "NO",
        "code": "+47",
        "name": "Norway"
    },
    {
        "iso2": "OM",
        "code": "+968",
        "name": "Oman"
    },
    {
        "iso2": "PK",
        "code": "+92",
        "name": "Pakistan"
    },
    {
        "iso2": "PW",
        "code": "+680",
        "name": "Palau"
    },
    {
        "iso2": "PS",
        "code": "+970",
        "name": "Palestinian Territories"
    },
    {
        "iso2": "PA",
        "code": "+507",
        "name": "Panama"
    },
    {
        "iso2": "PG",
        "code": "+675",
        "name": "Papua New Guinea"
    },
    {
        "iso2": "PY",
        "code": "+595",
        "name": "Paraguay"
    },
    {
        "iso2": "PE",
        "code": "+51",
        "name": "Peru"
    },
    {
        "iso2": "PH",
        "code": "+63",
        "name": "Philippines"
    },
    {
        "iso2": "PL",
        "code": "+48",
        "name": "Poland"
    },
    {
        "iso2": "PT",
        "code": "+351",
        "name": "Portugal"
    },
    {
        "iso2": "PR",
        "code": "+1",
        "name": "Puerto Rico"
    },
    {
        "iso2": "QA",
        "code": "+974",
        "name": "Qatar"
    },
    {
        "iso2": "RE",
        "code": "+262",
        "name": "R\u00e9union"
    },
    {
        "iso2": "RO",
        "code": "+40",
        "name": "Romania"
    },
    {
        "iso2": "RU",
        "code": "+7",
        "name": "Russia"
    },
    {
        "iso2": "RW",
        "code": "+250",
        "name": "Rwanda"
    },
    {
        "iso2": "WS",
        "code": "+685",
        "name": "Samoa"
    },
    {
        "iso2": "SM",
        "code": "+378",
        "name": "San Marino"
    },
    {
        "iso2": "ST",
        "code": "+239",
        "name": "S\u00e3o Tom\u00e9 & Pr\u00edncipe"
    },
    {
        "iso2": "SA",
        "code": "+966",
        "name": "Saudi Arabia"
    },
    {
        "iso2": "SN",
        "code": "+221",
        "name": "Senegal"
    },
    {
        "iso2": "RS",
        "code": "+381",
        "name": "Serbia"
    },
    {
        "iso2": "SC",
        "code": "+248",
        "name": "Seychelles"
    },
    {
        "iso2": "SL",
        "code": "+232",
        "name": "Sierra Leone"
    },
    {
        "iso2": "SG",
        "code": "+65",
        "name": "Singapore"
    },
    {
        "iso2": "SX",
        "code": "+1",
        "name": "Sint Maarten"
    },
    {
        "iso2": "SK",
        "code": "+421",
        "name": "Slovakia"
    },
    {
        "iso2": "SI",
        "code": "+386",
        "name": "Slovenia"
    },
    {
        "iso2": "SB",
        "code": "+677",
        "name": "Solomon Islands"
    },
    {
        "iso2": "SO",
        "code": "+252",
        "name": "Somalia"
    },
    {
        "iso2": "ZA",
        "code": "+27",
        "name": "South Africa"
    },
    {
        "iso2": "KR",
        "code": "+82",
        "name": "South Korea"
    },
    {
        "iso2": "SS",
        "code": "+211",
        "name": "South Sudan"
    },
    {
        "iso2": "ES",
        "code": "+34",
        "name": "Spain"
    },
    {
        "iso2": "LK",
        "code": "+94",
        "name": "Sri Lanka"
    },
    {
        "iso2": "BL",
        "code": "+590",
        "name": "St. Barth\u00e9lemy"
    },
    {
        "iso2": "SH",
        "code": "+290",
        "name": "St. Helena"
    },
    {
        "iso2": "KN",
        "code": "+1",
        "name": "St. Kitts & Nevis"
    },
    {
        "iso2": "LC",
        "code": "+1",
        "name": "St. Lucia"
    },
    {
        "iso2": "MF",
        "code": "+590",
        "name": "St. Martin"
    },
    {
        "iso2": "PM",
        "code": "+508",
        "name": "St. Pierre & Miquelon"
    },
    {
        "iso2": "VC",
        "code": "+1",
        "name": "St. Vincent & Grenadines"
    },
    {
        "iso2": "SD",
        "code": "+249",
        "name": "Sudan"
    },
    {
        "iso2": "SR",
        "code": "+597",
        "name": "Suriname"
    },
    {
        "iso2": "SJ",
        "code": "+47",
        "name": "Svalbard & Jan Mayen"
    },
    {
        "iso2": "SE",
        "code": "+46",
        "name": "Sweden"
    },
    {
        "iso2": "CH",
        "code": "+41",
        "name": "Switzerland"
    },
    {
        "iso2": "SY",
        "code": "+963",
        "name": "Syria"
    },
    {
        "iso2": "TW",
        "code": "+886",
        "name": "Taiwan"
    },
    {
        "iso2": "TJ",
        "code": "+992",
        "name": "Tajikistan"
    },
    {
        "iso2": "TZ",
        "code": "+255",
        "name": "Tanzania"
    },
    {
        "iso2": "TH",
        "code": "+66",
        "name": "Thailand"
    },
    {
        "iso2": "TL",
        "code": "+670",
        "name": "Timor-Leste"
    },
    {
        "iso2": "TG",
        "code": "+228",
        "name": "Togo"
    },
    {
        "iso2": "TK",
        "code": "+690",
        "name": "Tokelau"
    },
    {
        "iso2": "TO",
        "code": "+676",
        "name": "Tonga"
    },
    {
        "iso2": "TT",
        "code": "+1",
        "name": "Trinidad & Tobago"
    },
    {
        "iso2": "TA",
        "code": "+290",
        "name": "Tristan da Cunha"
    },
    {
        "iso2": "TN",
        "code": "+216",
        "name": "Tunisia"
    },
    {
        "iso2": "TR",
        "code": "+90",
        "name": "T\u00fcrkiye"
    },
    {
        "iso2": "TM",
        "code": "+993",
        "name": "Turkmenistan"
    },
    {
        "iso2": "TC",
        "code": "+1",
        "name": "Turks & Caicos Islands"
    },
    {
        "iso2": "TV",
        "code": "+688",
        "name": "Tuvalu"
    },
    {
        "iso2": "VI",
        "code": "+1",
        "name": "U.S. Virgin Islands"
    },
    {
        "iso2": "UG",
        "code": "+256",
        "name": "Uganda"
    },
    {
        "iso2": "UA",
        "code": "+380",
        "name": "Ukraine"
    },
    {
        "iso2": "AE",
        "code": "+971",
        "name": "United Arab Emirates"
    },
    {
        "iso2": "GB",
        "code": "+44",
        "name": "United Kingdom"
    },
    {
        "iso2": "US",
        "code": "+1",
        "name": "United States"
    },
    {
        "iso2": "UY",
        "code": "+598",
        "name": "Uruguay"
    },
    {
        "iso2": "UZ",
        "code": "+998",
        "name": "Uzbekistan"
    },
    {
        "iso2": "VU",
        "code": "+678",
        "name": "Vanuatu"
    },
    {
        "iso2": "VA",
        "code": "+39",
        "name": "Vatican City"
    },
    {
        "iso2": "VE",
        "code": "+58",
        "name": "Venezuela"
    },
    {
        "iso2": "VN",
        "code": "+84",
        "name": "Vietnam"
    },
    {
        "iso2": "WF",
        "code": "+681",
        "name": "Wallis & Futuna"
    },
    {
        "iso2": "EH",
        "code": "+212",
        "name": "Western Sahara"
    },
    {
        "iso2": "YE",
        "code": "+967",
        "name": "Yemen"
    },
    {
        "iso2": "ZM",
        "code": "+260",
        "name": "Zambia"
    },
    {
        "iso2": "ZW",
        "code": "+263",
        "name": "Zimbabwe"
    }
];

    var selectedCountry = guestPhoneCountryData.find(function(c) { return c.iso2 === 'US'; }) || guestPhoneCountryData[0];

    function setSelectedCountry(country) {
        selectedCountry = country;

        // Hidden input that actually submits with the form
        if (guestPhoneCountryCode) guestPhoneCountryCode.value = country.code;

        // Visible flag + dial code inside the button label
        var flagSpan = document.getElementById("guest-phone-country-flag");
        var dialSpan = document.getElementById("guest-phone-country-dial");

        if (flagSpan) {
            flagSpan.innerHTML = '';
            var img = document.createElement('img');
            img.src = flagUrl(country.iso2);
            img.alt = country.iso2;
            img.style.width = '20px';
            img.style.height = '14px';
            img.style.objectFit = 'cover';
            img.style.display = 'inline-block';
            flagSpan.appendChild(img);
        }

        if (dialSpan) dialSpan.textContent = country.code;

        guestPhoneInputs.forEach(function(input) {
            input.dataset.dialCode = country.code;
            input.dataset.iso2 = country.iso2;
        });
    }

    function renderCountryList(filterText) {
        if (!guestPhoneCountryList) return;
        guestPhoneCountryList.innerHTML = '';
        var filter = (filterText || '').trim().toLowerCase();
        var list = guestPhoneCountryData.filter(function(c) {
            if (!filter) return true;
            return c.name.toLowerCase().indexOf(filter) !== -1 ||
                   c.code.toLowerCase().indexOf(filter) !== -1 ||
                   c.iso2.toLowerCase().indexOf(filter) !== -1;
        });
        list.forEach(function(country) {
            var item = document.createElement('div');
            item.className = 'flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-gray-100';
            item.setAttribute('data-country-code', country.iso2);

            var img = document.createElement('img');
            img.src = flagUrl(country.iso2);
            img.alt = country.iso2;
            img.style.width = '20px';
            img.style.height = '14px';
            img.style.objectFit = 'cover';

            var label = document.createElement('span');
            label.textContent = country.name + ' (' + country.code + ')';

            item.appendChild(img);
            item.appendChild(label);

            item.addEventListener('click', function() {
                setSelectedCountry(country);
                if (guestPhoneCountryMenu) guestPhoneCountryMenu.classList.add('hidden');
            });

            guestPhoneCountryList.appendChild(item);
        });
    }

    function renderCountryMenu() {
        renderCountryList('');
    }

    if (guestPhoneCountryButton && guestPhoneCountryMenu) {
        guestPhoneCountryButton.addEventListener('click', function(e) {
            e.stopPropagation();
            guestPhoneCountryMenu.classList.toggle('hidden');
            if (!guestPhoneCountryMenu.classList.contains('hidden')) {
                renderCountryMenu();
                if (guestPhoneCountrySearch) guestPhoneCountrySearch.focus();
            }
        });
        document.addEventListener('click', function(e) {
            if (!guestPhoneCountryMenu.contains(e.target) && e.target !== guestPhoneCountryButton) {
                guestPhoneCountryMenu.classList.add('hidden');
            }
        });
    }

    if (guestPhoneCountrySearch) {
        guestPhoneCountrySearch.addEventListener('input', function(e) {
            renderCountryList(e.target.value);
        });
    }

    // Initialize
    setSelectedCountry(selectedCountry);
    renderCountryMenu();
})();
