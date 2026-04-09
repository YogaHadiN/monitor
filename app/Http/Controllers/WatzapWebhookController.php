<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Whatsapp\Parsers\WatzapInboundParser;
use App\Services\Whatsapp\ChatbotService;

class WatzapWebhookController extends Controller
{
    public function handle(
        Request $request,
        WatzapInboundParser $parser,
        ChatbotService $chatbot
    ) {
        $incoming = $parser->parse($request);

        if (!$incoming->phone) {
            return response()->json([
                'status' => false,
                'message' => 'phone not found'
            ], 422);
        }

        $chatbot->handleIncoming($incoming);

        return response()->json([
            'status' => true
        ]);
    }
}
