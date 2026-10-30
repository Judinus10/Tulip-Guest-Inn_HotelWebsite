<?php
declare(strict_types=1);

/** Sample data only; no real customer information or enquiry writes. */
function tulip_contact_sample(): array
{
    return ['name'=>'Sample Customer','email'=>'customer@example.test','phone'=>'+94 77 000 0000',
        'reference'=>'INQ-SAMPLE','subject'=>'Question about a family stay',
        'message'=>"Hello,\nCould you let us know whether a family room is available for our visit?\nThank you."];
}
