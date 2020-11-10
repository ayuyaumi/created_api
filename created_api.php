<?php 

	//change compound status from 'Belum Bayar' to 'Berbayar' and 'Batal'
	public function change_payment_status(Request $request)
    {
        $kpd = array();

        $dateS = Carbon::createFromFormat('Y-m-d', "2020-04-05");
        $start = $dateS->copy()->startOfDay();
        $dateE = Carbon::createFromFormat('Y-m-d', "2020-04-10");
        $end = $dateE->copy()->EndOfDay();

        // $end = Carbon::today();
        // $kompaun = Compound::whereBetween('tarikh_bayar', array($start, $end))->get();

        $summons = json_decode(file_get_contents('https://mdch.sipadu.my/compound_test_new.json'));

        foreach ($summons->data as $key => $i)
        {          
            
            $data = Compound::where('kpd', $i->kpd)->first();
            
            //check if summons status is 'Berbayar' in compound_test_new.json
            if($i->status == 'Berbayar')        
            {
                //if status in summons != status in data
                if(!($i->status == $data->status))  
                {
                    $kpd[] = $i->kpd;     //store the list of kpd changed in arrayy
                    $data->status = $i->status;
                    $data->amount_payment = $i->amount_payment;
                    $data->receipt = $i->receipt;
                    $data->tarikh_bayar = $i->tarikh_bayar;
                    $data->update_by = $i->update_by;
                    $data->save();
                }                

            }


            if((!empty($data)) and ($i->status == 'Batal'))
            {
                // if status in summons != status in data
                if(!($i->status == $data->status))  
                {                
                    $kpd[] = $i->kpd;     //store the list of kpd changed in arrayy
                    $data->status = $i->status;
                    $data->amount_payment = $i->amount_payment;
                    // $data->updated_at = $i->updated_at;
                    $data->catatan_dari_admin = $i->catatan_dari_admin;
                    $data->tarikh_bayar = $i->tarikh_bayar;
                    $data->update_by = $i->update_by;
                    $data->save();
                }   
            }
        }
        
        return 'kompaun batal at'. json_encode($kpd). ' total ade '. count($kpd);
    }

    //report compound tertunggak export to excel
    public function jumlah_kopmaun_tahunan($year, $jabatan)
    {
        $date = Carbon::createFromDate($year, 01, 01);
        $startOfYear = $date->copy()->startOfYear();
        $endOfYear = $date->copy()->endOfYear();  

        //list of compound 'Belum Bayar' in the year 2019        
        $compound = Compound::whereBetween('created_at', array($startOfYear, $endOfYear))->where('jbkod', $jabatan)->where('status', 'Belum Bayar')->get();

        $jumlah_kompaun = 0;
        $keping_kompaun = 0;

        foreach($compound as $c)
        {
            
            $jumlah_kompaun += $c->jumlah_asal_kompaun;
            $keping_kompaun += 1;
        }

        $result = array();
        array_push($result, array(
            'jumlah_kompaun' => $jumlah_kompaun,
            'keping_kompaun' => $keping_kompaun,
        ));

        return json_encode($result);
    }

    //Calculate compound with status 'Berbayar'
    public function kompaun_bayar_by_bulan($year, $month, $jabatan)
    {
        $monthly = Carbon::createFromDate($year, $month, 1);
        $startOfMonth = $monthly->copy()->startOfMonth();
        $endOfMonth = $monthly->copy()->endOfMonth();

        $monthly_collection = Compound::whereBetween('created_at', array($startOfMonth, $endOfMonth))->where('jbkod', $jabatan)->where('status', 'Berbayar')->get();
        
        $jumlah_bayar = 0;
        $keping_bayar = 0;
        $jumlah_asal = 0;
        $jumlah_kurang = 0;
        $temp_jumlah_kurang = 0;        

        foreach($monthly_collection as $k)
        {
            if((!($k->jumlah_kemaskini_kompaun == '')))
            {
                $temp_jumlah_kurang = $k->jumlah_asal_kompaun - $k->jumlah_kemaskini_kompaun;
            }

            $keping_bayar += 1; 
            $jumlah_asal += $k->jumlah_asal_kompaun;
            $jumlah_bayar += $k->amount_payment;
            $jumlah_kurang += $temp_jumlah_kurang;
        } 

        $result = array();
        array_push($result, array(
            'jumlah_berbayar' => $jumlah_bayar,
            'keping_berbayar' => $keping_bayar,
            'jumlah_pengurangan' => $jumlah_kurang,
            'jumlah_asal_berbayar' => $jumlah_asal,
        ));

        return json_encode($result);
    }

    public function berbayar_terkumpul($value1, $value2)
    {
        return $value1 += $value2;
    }

    public function baki_tunggakan($baki_tertunggak, $bayar_bulanan, $jumlah_kurang)
    {
        return $baki_tertunggak - $bayar_bulanan - $jumlah_kurang;
    }

    public function keping_tunggakan($keping_tertunggak, $keping_bulanan)
    {
        return $keping_tertunggak -= $keping_bulanan;
    }

    public function peratus_kutipan($jumlah_tertunggak, $berbayar_bulanan)
    {
        return $berbayar_bulanan /= $jumlah_tertunggak;
    }

    public function peratus_terkumpul($jumlah_tertunggak, $berbayar_terkumpul)
    {
        return $berbayar_terkumpul /= $jumlah_tertunggak;
    }

    public function pengurangan_terkumpul($total_pengurangan, $pengurangan_bulanan)
    {
        return $total_pengurangan += $pengurangan_bulanan;
    }

    public function export_compound_backlog($year, $jabatan)
    {
        $looping_month = array();

        // search kompaun tertunggak a year before
        $tertunggak = json_decode($this->jumlah_kopmaun_tahunan($year, $jabatan));  

        //declaring required variables
        $kutipan_terkumpul = 0;
        $tunggakan_terkumpul = 0;
        $pengurangan_terkumpul = 0;
        $baki_tunggakan = 0;
        $keping_tunggakan = 0;
        $baki_tunggakan = $tertunggak[0]->jumlah_kompaun;
        $keping_tunggakan = $tertunggak[0]->keping_kompaun;

        for( $month = 1; $month < 13; $month++)
        {
            //calling required functions
            $bayar_bulanan = json_decode($this->kompaun_bayar_by_bulan($year, $month,$jabatan));
            $kutipan_terkumpul = $this->berbayar_terkumpul($kutipan_terkumpul, $bayar_bulanan[0]->jumlah_berbayar);
            $baki_tunggakan = $this->baki_tunggakan($baki_tunggakan, $bayar_bulanan[0]->jumlah_berbayar, $bayar_bulanan[0]->jumlah_pengurangan);
            $keping_tunggakan = $this->keping_tunggakan($keping_tunggakan, $bayar_bulanan[0]->keping_berbayar);
            $peratus_kutipan = $this->peratus_kutipan($tertunggak[0]->jumlah_kompaun, $bayar_bulanan[0]->jumlah_berbayar);
            $peratus_terkumpul = $this->peratus_terkumpul($tertunggak[0]->jumlah_kompaun, $kutipan_terkumpul);
            $pengurangan_terkumpul = $this->pengurangan_terkumpul($pengurangan_terkumpul, $bayar_bulanan[0]->jumlah_pengurangan);

            array_push($looping_month, array(
                'bulan' => $month,
                'kutipan_bulanan(RM)' => $bayar_bulanan[0]->jumlah_berbayar,
                'jumlah_kompaun_bayar' => $bayar_bulanan[0]->keping_berbayar,
                'kutipan_terkumpul(RM)' => $kutipan_terkumpul,
                'baki_tunggakan(RM)' => $baki_tunggakan,
                'baki_belum_bayar' => $keping_tunggakan,
                'jumlah_kurang(RM)' => $bayar_bulanan[0]->jumlah_pengurangan,
                '%_kutipan' => number_format((float)$peratus_kutipan, 3, '.', ''),
                '%_terkumpul' => number_format((float)$peratus_terkumpul, 3, '.', ''),
            ));
        }

        $all = array();
        array_push($all, array(
            'tertunggak' => $tertunggak,
            'bulan' => $looping_month,
            'kutipan_terkumpul' => $kutipan_terkumpul,
            'pengurangan_terkumpul' => $pengurangan_terkumpul,
        ));

        dd($all);
    }

 ?>
