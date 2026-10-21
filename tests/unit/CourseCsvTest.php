<?php

use App\Libraries\CourseCsv;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CourseCsvTest extends CIUnitTestCase
{
    private function sampleCourse(): array
    {
        return [
            'id' => 7, 'name' => 'Southridge Golf Course', 'description' => "Nine holes, \"friendly\", fast greens",
            'address' => '5750 S Lemay Ave', 'city' => 'Fort Collins', 'state' => 'CO', 'zip' => '80525',
            'phone' => '970-226-2828', 'email' => null, 'website' => 'https://www.example.com/golf', 'image' => null, 'rating' => '33.2',
            'front_slope' => '113', 'back_slope' => '115', 'active' => '1',
        ];
    }

    private function sampleHoles(): array
    {
        $holes = [];
        foreach ([2, 1] as $n) { // out of order on purpose
            $holes[] = [
                'number' => $n, 'blue_yardage' => 300 + $n, 'blue_par' => 4, 'white_yardage' => 290 + $n, 'white_par' => 4,
                'red_yardage' => 250 + $n, 'red_par' => 4, 'handicap' => $n, 'net' => null, 'adj' => -1, 'image' => null,
            ];
        }

        return $holes;
    }

    public function testExportLayout(): void
    {
        $csv   = CourseCsv::export($this->sampleCourse(), $this->sampleHoles());
        $lines = explode("\n", rtrim($csv));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Starts with a byte order mark for Excel');
        $this->assertSame("\xEF\xBB\xBFfield,value", $lines[0]);
        $this->assertSame('name,"Southridge Golf Course"', $lines[1]);
        $this->assertContains('description,"Nine holes, ""friendly"", fast greens"', $lines);
        $this->assertContains('rating,33.2', $lines);
        $this->assertContains('website,https://www.example.com/golf', $lines);
        $this->assertSame('email,', $lines[array_search('website,https://www.example.com/golf', $lines, true) - 1], 'Website comes after email');
        $this->assertNotContains('id,7', $lines, 'The database id is not exported');

        $header = array_search('number,blue_yardage,blue_par,white_yardage,white_par,red_yardage,red_par,handicap,net,adj,image', $lines, true);
        $this->assertNotFalse($header, 'Hole header row');
        $this->assertSame('', $lines[$header - 1], 'Blank line before the holes');
        $this->assertSame('1,301,4,291,4,251,4,1,,-1,', $lines[$header + 1], 'Hole 1 comes first');
        $this->assertSame('2,302,4,292,4,252,4,2,,-1,', $lines[$header + 2]);
    }

    public function testExportThenParseGivesTheSameValues(): void
    {
        $parsed = CourseCsv::parse(CourseCsv::export($this->sampleCourse(), $this->sampleHoles()));

        $this->assertSame([], $parsed['errors']);
        $this->assertSame('Southridge Golf Course', $parsed['course']['name']);
        $this->assertSame('Nine holes, "friendly", fast greens', $parsed['course']['description']);
        $this->assertNull($parsed['course']['email']);
        $this->assertSame('https://www.example.com/golf', $parsed['course']['website']);
        $this->assertSame([1, 2], array_keys($parsed['holes']));
        $this->assertSame('291', $parsed['holes'][1]['white_yardage']);
        $this->assertSame('-1', $parsed['holes'][2]['adj']);
        $this->assertSame('', $parsed['holes'][2]['net']);
    }

    public function testParseAcceptsSpreadsheetEdits(): void
    {
        // Columns in a different order, only some of them, and Windows line endings.
        $csv    = "Field,Value\r\nName,Edited Course\r\n\r\nNumber,Handicap,White_Par\r\n3,9,5\r\n";
        $parsed = CourseCsv::parse($csv);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(['name' => 'Edited Course'], $parsed['course']);
        $this->assertSame([3 => ['handicap' => '9', 'white_par' => '5']], $parsed['holes']);
    }

    public function testParseReportsProblems(): void
    {
        $csv    = "field,value\nname,X\ncolour,green\n\nnumber,handicap,tee_box\n0,1\n4,2\n4,3\n";
        $errors = CourseCsv::parse($csv)['errors'];

        $this->assertContains('Line 3: unknown course field "colour".', $errors);
        $this->assertContains('Unknown hole column(s): tee_box.', $errors);
        $this->assertContains('Line 6: hole number must be 1 to 18.', $errors);
        $this->assertContains('Line 8: hole 4 appears more than once.', $errors);

        $this->assertContains('No course details or holes were found in the file.', CourseCsv::parse("\n\n")['errors']);
    }
}
