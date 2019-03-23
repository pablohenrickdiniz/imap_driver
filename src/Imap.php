<?php
namespace Imap;
/** This class can do certain imap commands, including custom commands, search etc.
 * Class Imap
 */
class Imap
{
    private $counter = 1;
    private $fp;
    private $lastError = null;

    public function init($host, $port)
    {
        if (!($this->fp = @fsockopen($host, $port, $errno, $errstr, 15))) {
            $this->lastError = $errstr;
            return false;
        }

        if (!@stream_set_timeout($this->fp, 15)) {
            $this->error = "Could not set timeout";
            return false;
        }

        return true;
    }

    public function login($login, $pwd)
    {
        return $this->command("LOGIN $login $pwd")['success'];
    }

    public function selectFolder($folder)
    {
        return $this->command("SELECT $folder")['success'];
    }

    public function search($criteria)
    {
        $ids = [];
        $response = $this->command("SEARCH $criteria");
        if($response['success']){
            if(isset($response['output'][0])){
                if(preg_match_all("/\d+/",$response['output'][0],$matches))
                    $ids = $matches[0];
            }
            return $ids;
        }
        return $ids;
    }

    private function fetch($uid,$section){
        $response = $this->command("FETCH $uid BODY.PEEK[$section]");
        if($response['success']){
            $output = implode("",$response['output']);
            if(preg_match("/^\*\s+$uid+\s+FETCH\s+\(BODY\[$section\]\s+\{[0-9]+\}\s*((.*\s*)*)\)/",$output,$matches)){
                return $matches[1];
            }
        }
        return false;
    }

    public function fetchText($uid){
        return $this->fetch($uid,'TEXT');
    }

    public function fetchHeaders($uid)
    {
        return $this->fetch($uid,'HEADER');
    }

    private function command($command)
    {
        $response = [
            'output' => [],
            'success' => false
        ];
        $counter = $this->count();
        if(@fwrite($this->fp, "$counter $command\r\n")){
            $lines = [];
            while ($line = @fgets($this->fp)) {
                if(preg_match("/^$counter\s+(OK|NO|BAD)(.*?)$/i",$line,$matches)){
                    $status = strtoupper($matches[1]);
                    if(in_array($status,['NO','BAD'])){
                        $response['success'] = false;
                        $response['error'] = $matches[2];
                        $this->lastError = $matches[2];
                        $this->close();
                    }
                    else{
                        $response['success'] = true;
                    }
                    break;
                }
                $lines[] = $line;
            }
            $response['output'] = array_filter($lines);
        }

        return $response;
    }

    private function count()
    {
        return sprintf('%08d', $this->counter++);
    }

    public function close()
    {
        @fclose($this->fp);
    }

    public function getLastError(){
        return $this->lastError;
    }
}