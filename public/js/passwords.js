/*
 * Password fields:
 *  - every password box gets a "Show password" tick box under it;
 *  - a box marked data-generate gets a "Generate" button by its label that
 *    fills in a random password made of three short words, e.g. "morebitdad".
 *    data-confirm="<id>" names a "type it again" box to fill in too.
 * Without JavaScript the boxes work as plain password boxes.
 */
(function () {
    'use strict';

    // Short, common, inoffensive words: three of them make an easy-to-type
    // password of 9 to 12 letters.
    var WORDS = (
        'ace act add age aid aim air all and ant any ape arm art ash ask bag bat bay bed bee big bin bit ' +
        'box boy bud bug bun bus cab cap car cat cob cod cow cub cup cut dad day den dew dig dim dog dot ' +
        'dry dug ear eat egg elf elm end eye fan far fat fig fin fit fix fly fog fox fun gap gas gem got ' +
        'gum hat hay hen hip hit hop hot hub hug hut ice ink jam jar jet jog joy key kid kit lab lap law ' +
        'leg lid lip log lot low mad map mat mix mop mud mug nap net new nod nut oak oar oat odd oil old ' +
        'owl own pad pan paw pea pen pet pie pig pin pit pod pop pot pub pun pup put rag ram ran rat raw ' +
        'red rib rid rim rod row rub rug run sad sap saw say sea set sip sit six sky sly sob sod son sow ' +
        'soy spa spy sun tab tag tan tap tar tea ten tie tin tip toe ton top toy tub tug two urn van vat ' +
        'vet wax web wet wig win wit yak yam yes yet zip zoo ' +
        'able acre aunt bake ball band bank barn base bath bead beak bean bear bell belt bend best bike ' +
        'bird blue boat body bold bolt bone book boot bowl brew buck bulb bump bush busy cake calm camp ' +
        'cape card care cart cash cave chef chin chip city clam clay clip club coal coat code coin cold ' +
        'cone cook cool cope cord corn cozy crab crew crop crow cube curl dark dart dash dawn deck deer ' +
        'desk dial dice dish dive dock dome door dove down drum duck dune dust easy echo edge epic face ' +
        'fact fair farm fast fawn feet fern film fine fire fish flag flat flip foam fold folk fond food ' +
        'foot fork form fort frog fuel full fund gate gear gift glad glow glue goat gold golf good gown ' +
        'grin grip gulf hail hair half hall hand harp hawk heap herb hero high hike hill hint hive hold ' +
        'home hood hook hope horn huge hunt idea iron isle jazz jump keen kelp kind king kite knot lake ' +
        'lamb lamp land lane lark leaf lily lime line lion list loaf loft long loop lute mail main malt ' +
        'mane mask meal melt mild milk mill mint mist mole moon more moss moth mule nest nice note oven ' +
        'pace pack page palm park path peak pear pine pink plan plum poem pond pony pool port pure quiz ' +
        'raft rain ramp reed reef rest rice ride ring road robe rock roof room root rope rose ruby safe ' +
        'sage sail salt sand seal seed ship shoe silk sing sled snow soap sock sofa soft song soup star ' +
        'stem step surf swan tail tame team tent tide tile time tiny toad tone tool tour town tree trip ' +
        'tune vase vest view vine wave weed well west wind wing wise wolf wood wool yard yarn year zero'
    ).split(' ');

    function randomInt(max) {
        var buf = new Uint32Array(1);
        var limit = Math.floor(0x100000000 / max) * max; // avoid bias
        do {
            window.crypto.getRandomValues(buf);
        } while (buf[0] >= limit);
        return buf[0] % max;
    }

    function generatePassword() {
        var words = [];
        for (var i = 0; i < 3; i++) {
            words.push(WORDS[randomInt(WORDS.length)]);
        }
        return words.join('');
    }

    var count = 0;

    function addShowToggle(input) {
        count++;
        var id = 'show_' + (input.id || 'password') + '_' + count;
        var wrap = document.createElement('div');
        wrap.className = 'field-check show-password';
        var box = document.createElement('input');
        box.type = 'checkbox';
        box.id = id;
        var label = document.createElement('label');
        label.htmlFor = id;
        label.textContent = 'Show password';
        box.addEventListener('change', function () {
            input.type = box.checked ? 'text' : 'password';
        });
        wrap.appendChild(box);
        wrap.appendChild(label);
        input.insertAdjacentElement('afterend', wrap);
        input.showToggle = box;
    }

    function show(input) {
        input.type = 'text';
        if (input.showToggle) {
            input.showToggle.checked = true;
        }
    }

    function addGenerateButton(input) {
        var label = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'link-button generate-password';
        button.textContent = 'Generate';
        button.title = 'Fill in a random password made of three short words';
        button.addEventListener('click', function () {
            var password = generatePassword();
            input.value = password;
            show(input);
            var confirmId = input.getAttribute('data-confirm');
            var confirm = confirmId ? document.getElementById(confirmId) : null;
            if (confirm) {
                confirm.value = password;
                show(confirm);
            }
            input.focus();
            input.select();
        });
        if (label) {
            // On the label's line, after its text.
            label.appendChild(button);
        } else {
            input.insertAdjacentElement('beforebegin', button);
        }
    }

    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        addShowToggle(input);
        if (input.hasAttribute('data-generate') && window.crypto && window.crypto.getRandomValues) {
            addGenerateButton(input);
        }
    });

    // For the tests: window.generatePassword() returns a new password.
    window.generatePassword = generatePassword;
})();
