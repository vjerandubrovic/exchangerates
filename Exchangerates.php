<?php require_once("database/db.php");

class Exchangerates{

    private $source;
    private $table;
    private $db;
    private $values;

    function __construct(){
        $this->db = new Database();
    }

    function getDailyExchangeRatePBZ(){
        $this->source = 'https://www.pbz.hr/Downloads/PBZteclist.xml';
        $xml = simplexml_load_file($this->source);
        $bank = $xml->ExchRate->Bank;
        $currency = $xml->ExchRate->Currency;
        $count = count($currency);
        $values = null;

        for ($i=0; $i<$count; $i++) { 
            $name = $currency[$i]->Name;
            $unit = $currency[$i]->Unit;
            $buyRate = $currency[$i]->BuyRateForeign;
            $meanRate = $currency[$i]->MeanRate;
            $sellRate = $currency[$i]->SellRateForeign;
            $values .= $name." ".$unit." ".$meanRate."-";
            $this->saveToDB($bank,$name,$unit,$buyRate,$meanRate,$sellRate);
        }
        $values = explode("-",$values);
        $this->values = $values;

    }

    function getDailyExchangeRateHNB(){

        $this->source = 'https://www.hnb.hr/tecajn/htecajn.htm';
        $bank = "HNB";
        $file = file_get_contents($this->source);
        $file = str_replace('       ', ' ', $file);
        $file = explode("\n",$file);
        $count = count($file);
        $values = null;

        for ($i=1; $i < $count; $i++) { 
            $file[$i] = explode(" ",$file[$i]);
            $name_unit = str_split($file[$i][0],3);
            $name = $name_unit[1];
            $unit = $name_unit[2];
            $buyRate = $file[$i][1];
            $meanRate = $file[$i][2];
            $sellRate = $file[$i][3];
            $values .= $name." ".$unit." ".$meanRate."-";
            $this->saveToDB($bank,$name,$unit,$buyRate,$meanRate,$sellRate);   
        }
        $values = explode("-",$values);
        $this->values = $values;
    }

    function saveToDB($bank,$name,$unit,$buyRate,$meanRate,$sellRate){

        if($bank == "Privredna banka Zagreb"){
            $table = 'valute_pbz';
        }else{
            $table = 'valute_hnb';
        }

        $d=date('d-m-Y');
        $this->table = $table.$d;
        $this->db->query("CREATE TABLE IF NOT EXISTS `$this->table` (valuta_id INT UNSIGNED AUTO_INCREMENT NOT NULL PRIMARY KEY, `Name` VARCHAR(50), `Unit`VARCHAR(50), `BuyRateForeign` VARCHAR(50), `MeanRate`VARCHAR(50),`SellRateForeign` VARCHAR(50)) ENGINE =InnoDB;");
        $this->db->execute();
        $this->db->query("INSERT INTO `$this->table`(`Name`, `Unit`, `BuyRateForeign`, `MeanRate`, `SellRateForeign`) VALUES (:vname,:unit,:buyRate,:meanRate,:sellRate)");
        $this->db->bind(':vname', $name);
        $this->db->bind(':unit', $unit);
        $this->db->bind(':buyRate', $buyRate);
        $this->db->bind(':meanRate', $meanRate);
        $this->db->bind(':sellRate', $sellRate);
        $this->db->execute();
    }

    function selectPrevious(){
        $d = date("d-m-Y", strtotime("-1 days"));
        $table = "valute_hnb";
        $table = $table.$d;
        $this->db->query("SELECT `MeanRate` FROM `$table`");
        return $this->db->resultSet();
    }

    function save($source,$date,$output){
        $valu=$this->selectPrevious();
        $csv = fopen($output,"w");
        $values = $this->values;
        $count = count($this->values);

        for ($i=0; $i < $count-1; $i++) { 
            $val = explode(" ",$values[$i]);      
            $a = $valu[$i]['MeanRate'];
            $a = str_replace(',','.', $a);
            $a = (float)$a;
            $b = $val[2];
            $b = str_replace(',','.', $b);
            $b = (float)$b;
            $razlika = ($a/($a+$b))-($b/($a+$b));
            array_push($val,$date,$razlika);
            fputcsv($csv,$val);
        }
        fclose($csv);
    }
}