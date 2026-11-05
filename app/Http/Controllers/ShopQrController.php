<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\QrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShopQrController extends Controller
{
    /**
     * Download the shop's QR code as a 1024px PNG.
     */
    public function png(Request $request): Response
    {
        $shop = $this->ownShop($request);

        return response(QrCode::png(route('board.show', $shop)), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$shop->slug.'-qr.png"',
        ]);
    }

    /**
     * Print-ready A5 table card (print or "Save as PDF" from the browser).
     */
    public function tableTent(Request $request): View
    {
        $shop = $this->ownShop($request);
        $url = route('board.show', $shop);

        return view('shop.table-tent', [
            'shop' => $shop,
            'url' => $url,
            'qr' => QrCode::svg($url, 560),
        ]);
    }

    private function ownShop(Request $request): Shop
    {
        $shop = $request->user()?->shop;

        abort_if($shop === null, 404);

        return $shop;
    }
}
