<?php

namespace PorterTest;

use nadar\quill\Lexer as Quill;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    /**
     * @covers FlarumImageEmbed::process
     */
    public function testQuillCanParseExternalEmbed(): void
    {
        $stored = '[{"insert":{"embed-external":{"data":{"url":' .
            '"https:\/\/example.com\/uploads\/779\/8C8NUCDYD6ZW.png","name":"auto-draft-6.png","type":"image",' .
            '"size":92198,"width":500,"height":545,"mediaID":34100,"dateInserted":"2020-01-11T03:21:13+00:00",' .
            '"insertUserID":4708,"foreignType":"embed","foreignID":4708,"format":null,"bodyRaw":null},' .
            '"loaderData":{"type":"image"}}}},{"insert":"\n"}]'; // post id 953302
        $stored = '{"ops":' . $stored . '}'; // Fix the JSON.
        $lexer = new Quill($stored);
        $lexer->registerListener(new \Porter\Parser\FlarumImageEmbed());
        $result = $lexer->render();
        $expected = '<UPL-IMAGE-PREVIEW url="https://example.com/uploads/779/8C8NUCDYD6ZW.png">' .
            '[upl-image-preview url=https://example.com/uploads/779/8C8NUCDYD6ZW.png]</UPL-IMAGE-PREVIEW>';
        $this->assertStringContainsString($expected, $result);
    }
    // postid 227957

    // postid 228220
//[{"insert":{"embed-external":{"data":{"recordID":227954,"recordType":"comment","body":"<p><a href=\"https:\/\/pmono.no\/discussion\/comment\/227952#Comment_227952\" rel=\"nofollow\">https:\/\/pmono.no\/discussion\/comment\/227952#Comment_227952<\/a><\/p><p>Er bare forn&aelig;rmet for at jeg ikke er med p&aring; skjemaet ditt.<\/p>","bodyRaw":[{"insert":"https:\/\/pmono.no\/discussion\/comment\/227952#Comment_227952","attributes":{"link":"https:\/\/pmono.no\/discussion\/comment\/227952#Comment_227952"}},{"insert":"\n"},{"insert":"Er bare fornærmet for at jeg ikke er med på skjemaet ditt. \n"}],"format":"Rich","dateInserted":"2021-10-11T11:41:46+00:00","insertUser":{"userID":2,"name":"thoreirik","photoUrl":"https:\/\/pmono.no\/uploads\/userpics\/882\/n4DN33MVJ31PH.jpg","dateLastActive":"2021-10-15T08:49:18+00:00"},"displayOptions":{"showUserLabel":false,"showCompactUserInfo":true,"showDiscussionLink":false,"showPostLink":false,"showCategoryLink":false,"renderFullContent":false,"expandByDefault":false},"url":"https:\/\/pmono.no\/discussion\/comment\/227954#Comment_227954","embedType":"quote"},"loaderData":{"type":"link","link":"https:\/\/pmono.no\/discussion\/comment\/227954#Comment_227954"}}}},{"insert":"Ble ferdig med skjemaet i går. Du kom akkurat inn på listen i den siste runden jeg la inn, som var årets første runde hvor du gav 12 poeng til Penguin Cafe Orchestra "},{"insert":{"emoji":{"emojiChar":"❤️"}}},{"insert":"\nHer er en rangering av hvor glad jeg er i dere i pgp-sammenheng:\n"},{"insert":{"mention":{"name":"MrHorse","userID":949}}},{"insert":" 6 (4 mottatt\/ 2 gitt)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"BorlängeHangover","userID":34}}},{"insert":" 5 (1 \/4)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"oddish","userID":903}}},{"insert":" 5 (2\/3)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"Stenseng","userID":30}}},{"insert":" 5 (4\/1)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"kebbster","userID":925}}},{"insert":" 4 (3\/1)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"Mytteristen","userID":927}}},{"insert":" 4 (1\/ 3)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"Erik","userID":17}}},{"insert":" 4 (0\/4)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"Sveinar_Finnebråten","userID":926}}},{"insert":" 3 (1\/2)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"Lillomarkstraveren","userID":951}}},{"insert":" 3 (3\/0)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"ArneKr","userID":3}}},{"insert":" 3 (2\/1)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"dogballs","userID":53}}},{"insert":" og "},{"insert":{"mention":{"name":"AG3","userID":78}}},{"insert":"  2 (0\/2)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"kenneth","userID":11}}},{"insert":" 2 (1\/1)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"ChillMagneBondevik","userID":914}}},{"insert":", "},{"insert":{"mention":{"name":"knøtten","userID":941}}},{"insert":", og "},{"attributes":{"mention-autocomplete":true},"insert":{"mention":{"name":"blomert","userID":203}}},{"attributes":{"mention-autocomplete":true},"insert":" 2 (2\/0)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"Jostein","userID":1}}},{"insert":", "},{"insert":{"mention":{"name":"Bjørn_Stad","userID":22}}},{"insert":", "},{"insert":{"mention":{"name":"Erikhans","userID":207}}},{"insert":", "},{"insert":{"mention":{"name":"Kyrrebust","userID":945}}},{"insert":" og "},{"insert":{"mention":{"name":"tilion","userID":16}}},{"insert":" 1 (0\/1)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":{"mention":{"name":"thoreirik","userID":2}}},{"insert":", "},{"insert":{"mention":{"name":"Hoa","userID":950}}},{"insert":", "},{"insert":{"mention":{"name":"kvan","userID":944}}},{"insert":" og "},{"insert":{"mention":{"name":"bubastis","userID":8}}},{"insert":" 1(1\/0)"},{"attributes":{"list":{"depth":0,"type":"ordered"}},"insert":"\n"},{"insert":"Gledelig overrasket over "},{"insert":{"mention":{"name":"BorlängeHangover","userID":34}}},{"insert":" og "},{"insert":{"mention":{"name":"Stenseng","userID":30}}},{"insert":". Tilsvarende skuffet over mange i motsatt ende av tabellen.\n\n"}]

}
