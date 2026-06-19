<?php

namespace Database\Seeders;

use App\Models\LocationGeoCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationGeoCacheSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            'metro manila'=>[14.5995,120.9842],'ncr'=>[14.5995,120.9842],'manila'=>[14.5995,120.9842],
            'abra'=>[17.5978,120.4268],'apayao'=>[18.0153,121.171],'aurora'=>[15.9784,121.6325],
            'bataan'=>[14.6416,120.4818],'batanes'=>[20.4487,121.9701],'batangas'=>[13.7565,121.0583],
            'benguet'=>[16.403,120.5955],'bulacan'=>[14.7943,120.8767],'cagayan'=>[17.6132,121.727],
            'camarines norte'=>[14.1389,122.7632],'camarines sur'=>[13.525,123.3486],
            'catanduanes'=>[13.7086,124.2421],'cavite'=>[14.2456,120.8787],'ifugao'=>[16.8306,121.171],
            'ilocos norte'=>[18.1647,120.7116],'ilocos sur'=>[17.5755,120.3869],'isabela'=>[16.9754,121.8107],
            'kalinga'=>[17.4766,121.3627],'la union'=>[16.6159,120.3209],'laguna'=>[14.2786,121.4113],
            'marinduque'=>[13.4767,121.9032],'masbate'=>[12.3696,123.6216],'mountain province'=>[16.9986,120.9397],
            'nueva ecija'=>[15.5784,121.0227],'nueva vizcaya'=>[16.3301,121.171],'occidental mindoro'=>[12.9695,120.6235],
            'oriental mindoro'=>[13.0565,121.4072],'palawan'=>[9.5,118.5],'pampanga'=>[15.0794,120.62],
            'pangasinan'=>[15.8949,120.2863],'quezon'=>[14.0313,122.1004],'quirino'=>[16.27,121.5374],
            'rizal'=>[14.6042,121.3084],'romblon'=>[12.5772,122.2694],'sorsogon'=>[12.9433,124.0047],
            'tarlac'=>[15.4755,120.596],'zambales'=>[15.5083,120.0693],'aklan'=>[11.8166,122.0942],
            'antique'=>[11.3683,122.064],'biliran'=>[11.588,124.4652],'bohol'=>[9.7506,124.2432],
            'capiz'=>[11.5521,122.7396],'cebu'=>[10.3157,123.8854],'eastern samar'=>[11.6508,125.4082],
            'guimaras'=>[10.6019,122.5956],'iloilo'=>[10.6969,122.5644],'leyte'=>[10.8782,124.883],
            'negros occidental'=>[10.2926,123.0249],'negros oriental'=>[9.631,123.0116],
            'northern samar'=>[12.5641,124.5553],'samar'=>[11.524,125.0036],'siquijor'=>[9.2076,123.5328],
            'southern leyte'=>[10.337,125.172],'agusan del norte'=>[8.9466,125.5315],
            'agusan del sur'=>[8.1603,126.0133],'basilan'=>[6.4228,121.969],'bukidnon'=>[7.8551,125.064],
            'camiguin'=>[9.1765,124.7259],'compostela valley'=>[7.5556,126.0765],'davao de oro'=>[7.5556,126.0765],
            'cotabato'=>[7.2048,124.7283],'north cotabato'=>[7.2048,124.7283],'south cotabato'=>[6.2969,124.8534],
            'davao'=>[7.1907,125.4553],'davao city'=>[7.1907,125.4553],'davao del norte'=>[7.5619,125.6549],
            'davao del sur'=>[6.7667,125.3284],'davao occidental'=>[6.1,125.6],'davao oriental'=>[7.3172,126.542],
            'dinagat islands'=>[10.1274,125.5984],'lanao del norte'=>[7.9725,124.2886],
            'lanao del sur'=>[7.8232,124.4357],'maguindanao'=>[6.9422,124.4335],
            'misamis occidental'=>[8.3375,123.7071],'misamis oriental'=>[8.5046,124.622],
            'sarangani'=>[5.963,125.199],'sultan kudarat'=>[6.5069,124.4169],'sulu'=>[5.9784,121.0331],
            'surigao del norte'=>[9.7177,125.595],'surigao del sur'=>[8.751,126.1378],'tawi-tawi'=>[5.1339,119.7659],
            'zamboanga del norte'=>[8.1527,123.2576],'zamboanga del sur'=>[7.8381,123.2968],
            'zamboanga sibugay'=>[7.5226,122.8509],
        ];

        $cities = [
            // NCR
            'quezon city'=>[14.676,121.0437],'manila'=>[14.5995,120.9842],'makati'=>[14.5547,121.0244],
            'pasig'=>[14.5764,121.0851],'taguig'=>[14.5243,121.0792],'caloocan'=>[14.6499,120.9674],
            'mandaluyong'=>[14.5794,121.0359],'marikina'=>[14.6507,121.1029],'paranaque'=>[14.4793,121.0198],
            'parañaque'=>[14.4793,121.0198],'las pinas'=>[14.4453,120.9929],'las piñas'=>[14.4453,120.9929],
            'malabon'=>[14.6625,120.9572],'navotas'=>[14.6667,120.9417],'valenzuela'=>[14.7011,120.983],
            'pasay'=>[14.5378,121.0014],'muntinlupa'=>[14.4081,121.0415],'san juan'=>[14.6019,121.0355],
            'pateros'=>[14.5453,121.0672],
            // Bulacan
            'malolos'=>[14.8433,120.8114],'meycauayan'=>[14.7362,120.96],'marilao'=>[14.7583,120.95],
            'bocaue'=>[14.798,120.9317],'balagtas'=>[14.8114,120.9081],'guiguinto'=>[14.8378,120.8803],
            'baliuag'=>[14.9553,120.8961],'san jose del monte'=>[14.8131,121.0453],'sta maria'=>[14.8083,121.0072],
            'santa maria'=>[14.8083,121.0072],'calumpit'=>[14.9142,120.7661],'hagonoy'=>[14.8306,120.7361],
            'plaridel'=>[14.8881,120.8608],'bulakan'=>[14.7958,120.9014],
            // Cavite
            'bacoor'=>[14.458,120.9644],'imus'=>[14.4292,120.9369],'dasmarinas'=>[14.3294,120.9367],
            'dasmariñas'=>[14.3294,120.9367],'general trias'=>[14.3867,120.8817],'trece martires'=>[14.2806,120.8583],
            'carmona'=>[14.3156,121.0583],'silang'=>[14.2306,120.9736],'tagaytay'=>[14.1053,120.962],
            'cavite city'=>[14.4791,120.8979],'noveleta'=>[14.4311,120.8814],'rosario'=>[14.4158,120.8492],
            // Laguna
            'calamba'=>[14.2118,121.1653],'san pablo'=>[14.0683,121.3253],'sta rosa'=>[14.3122,121.1113],
            'santa rosa'=>[14.3122,121.1113],'binan'=>[14.3392,121.0783],'cabuyao'=>[14.2757,121.1254],
            'san pedro'=>[14.3583,121.0569],'bay'=>[14.1822,121.2853],'los banos'=>[14.1672,121.2436],
            'los baños'=>[14.1672,121.2436],'pagsanjan'=>[14.2708,121.4569],
            // Rizal
            'antipolo'=>[14.6286,121.176],'cainta'=>[14.5836,121.1228],'taytay'=>[14.5602,121.1326],
            'angono'=>[14.5253,121.1536],'binangonan'=>[14.4667,121.1831],'rodriguez'=>[14.7372,121.1164],
            'morong'=>[14.5153,121.2417],'tanay'=>[14.4956,121.2847],'san mateo'=>[14.6994,121.1225],
            // Pampanga
            'san fernando'=>[15.0289,120.691],'angeles'=>[15.145,120.596],'mabalacat'=>[15.2167,120.5667],
            'mexico'=>[15.0656,120.7194],'lubao'=>[14.9333,120.6],'apalit'=>[14.9511,120.7603],
            // Batangas
            'lipa'=>[13.9411,121.1631],'batangas city'=>[13.7565,121.0583],'tanauan'=>[14.0856,121.1503],
            'santo tomas'=>[14.1067,121.1408],'nasugbu'=>[14.0711,120.6369],'balayan'=>[13.9375,120.7294],
            // Pangasinan
            'dagupan'=>[16.043,120.3336],'san carlos'=>[15.9266,120.35],'urdaneta'=>[15.976,120.5714],
            'lingayen'=>[16.0167,120.2333],'alaminos'=>[16.1567,119.9814],
            // Ilocos
            'vigan'=>[17.5747,120.3875],'laoag'=>[18.1977,120.5937],'candon'=>[17.1939,120.4489],
            // CAR
            'baguio'=>[16.4023,120.596],'baguio city'=>[16.4023,120.596],'la trinidad'=>[16.4615,120.5878],
            // Cagayan Valley
            'tuguegarao'=>[17.6132,121.727],'ilagan'=>[17.1494,121.8939],'santiago'=>[16.6857,121.5494],
            'cauayan'=>[16.9231,121.7667],
            // Nueva Ecija
            'cabanatuan'=>[15.488,120.9717],'gapan'=>[15.3078,120.9469],'olongapo'=>[14.8292,120.2828],
            // Quezon Province
            'lucena'=>[13.9328,121.6172],'tayabas'=>[14.0222,121.5844],
            // Bicol
            'naga'=>[13.6192,123.1814],'legazpi'=>[13.1391,123.7438],'sorsogon city'=>[12.9433,124.0047],
            'iriga'=>[13.4247,123.4083],'tabaco'=>[13.3592,123.7319],
            // Cebu
            'cebu city'=>[10.3157,123.8854],'mandaue'=>[10.3236,123.9223],'lapu-lapu'=>[10.3103,123.9494],
            'lapu lapu'=>[10.3103,123.9494],'talisay cebu'=>[10.2444,123.8481],'danao'=>[10.5209,124.0267],
            'toledo'=>[10.3753,123.6381],'carcar'=>[10.1064,123.6403],'consolacion'=>[10.3731,123.9614],
            'minglanilla'=>[10.2419,123.7931],
            // Negros Occidental
            'bacolod'=>[10.6764,122.9563],'silay'=>[10.8003,122.9733],'victorias'=>[10.9003,123.0747],
            'la carlota'=>[10.4253,122.9183],'bago'=>[10.5333,122.8333],'kabankalan'=>[9.9875,122.8136],
            'sagay'=>[10.8942,123.4192],'cadiz'=>[11.0297,123.3036],
            // Iloilo
            'iloilo city'=>[10.6969,122.5644],'passi'=>[11.1031,122.6403],'oton'=>[10.6969,122.4869],
            // Leyte
            'tacloban'=>[11.2543,125.0],'ormoc'=>[11.0064,124.6078],'baybay'=>[10.6817,124.8025],
            // Bohol
            'tagbilaran'=>[9.6478,123.8554],'ubay'=>[10.0511,124.4728],
            // Negros Oriental
            'dumaguete'=>[9.3068,123.3054],'bais'=>[9.5886,123.1283],
            // Davao Region
            'davao city'=>[7.1907,125.4553],'tagum'=>[7.4478,125.8078],'panabo'=>[7.3089,125.6837],
            'digos'=>[6.7494,125.3572],'mati'=>[6.9511,126.2184],
            // Northern Mindanao
            'cagayan de oro'=>[8.4542,124.6319],'iligan'=>[8.2285,124.2453],'gingoog'=>[8.8222,125.0953],
            // Misamis Occidental
            'ozamiz'=>[8.1489,123.8419],'oroquieta'=>[8.4853,123.8028],
            // Zamboanga Peninsula
            'zamboanga city'=>[6.9214,122.079],'dipolog'=>[8.5858,123.3411],'pagadian'=>[7.8267,123.4367],
            'dapitan'=>[8.6544,123.4247],
            // SOCCSKSARGEN
            'general santos'=>[6.1164,125.1716],'koronadal'=>[6.5033,124.8436],'kidapawan'=>[7.0083,125.0892],
            'tacurong'=>[6.6933,124.6758],
            // North Cotabato
            'cotabato city'=>[7.2236,124.246],'midsayap'=>[7.1903,124.5297],
            // Surigao
            'surigao city'=>[9.7848,125.4933],'tandag'=>[9.0728,126.1972],
            // Agusan
            'butuan'=>[8.9475,125.5406],'cabadbaran'=>[9.1231,125.535],
            // Lanao
            'marawi'=>[7.9986,124.2928],
            // Bukidnon
            'malaybalay'=>[8.1575,125.1272],'valencia'=>[7.9042,125.0944],
        ];

        $rows = [];

        // Also seed the 3 provinces Nominatim fails to find by name
        $provinces += [
            'albay'        => [13.1775, 123.5280],
            'tawi tawi'    => [5.1339,  119.7659],
            'western samar'=> [11.524,  125.0036],
        ];

        foreach ($provinces as $name => $coords) {
            $rows[] = [
                'name_key'     => LocationGeoCache::makeKey($name, 'province'),
                'display_name' => $name,
                'level'        => 'province',
                'lat'          => $coords[0],
                'lng'          => $coords[1],
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        foreach ($cities as $name => $coords) {
            $rows[] = [
                'name_key'     => LocationGeoCache::makeKey($name, 'city'),
                'display_name' => $name,
                'level'        => 'city',
                'lat'          => $coords[0],
                'lng'          => $coords[1],
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        // Insert in chunks, ignore duplicates
        foreach (array_chunk($rows, 100) as $chunk) {
            LocationGeoCache::upsert($chunk, ['name_key'], ['lat', 'lng', 'updated_at']);
        }

        $this->command->info('Seeded ' . count($rows) . ' location coordinates.');
    }
}
