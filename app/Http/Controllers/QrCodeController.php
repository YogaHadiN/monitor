<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;


use Input;
/* use Builder; */
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Alignment\LabelAlignmentLeft;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Label\Label;


class QrCodeController extends Controller
{
    public function index(){
		$result = Builder::create()
			->writer(new PngWriter())
			->writerOptions([])
			->data( Input::get('text') )
			->encoding(new Encoding('UTF-8'))
			->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
			->size(200)
			->margin(1)
			->roundBlockSizeMode(new RoundBlockSizeModeMargin())
			/* ->logoPath(__DIR__.'/assets/symfony.png') */
			/* ->labelText('This is the label') */
			/* ->labelFont(new NotoSans(20)) */
			/* ->labelAlignment(new LabelAlignmentCenter()) */
			->build();
		return $result->getString();
    }

    public function inPdf($text){
		$result = Builder::create()
			->writer(new PngWriter())
			->writerOptions([])
			->data($text)
			->encoding(new Encoding('UTF-8'))
			->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
			->size(200)
			->margin(1)
			->roundBlockSizeMode(new RoundBlockSizeModeMargin())
			/* ->logoPath(__DIR__.'/assets/symfony.png') */
			/* ->labelText('This is the label') */
			/* ->labelFont(new NotoSans(20)) */
			/* ->labelAlignment(new LabelAlignmentCenter()) */
			->build();
		return $result->getDataUri();
    }

    public function testQrCode(){

        $text = 'Pret is pret is pret';

        $qr_code = QrCode::create($text)
                         ->setSize(600)
                         ->setMargin(40)
                         ->setForegroundColor(new Color(255, 128, 0))
                         ->setBackgroundColor(new Color(153, 204, 255))
                         ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh);

        $label = Label::create("This is a label")
                      ->setTextColor(new Color(255, 0, 0))
                      ->setAlignment(new LabelAlignmentLeft);


        $writer = new PngWriter;

        $result = $writer->write($qr_code);

        $result->saveToFile("qr-code.png");
        // Output the QR code image to the browser
        /* header("Content-Type: " . $result->getMimeType()); */

        \Storage::disk('s3')->put($destination_path. $filename, file_get_contents($upload_cover));

        /* echo $result->getString(); */

        // Save the image to a file
        /* $result->saveToFile("qr-code.png"); */
    }
    
}

