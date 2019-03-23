<?php
namespace Imap;
/** This class can do certain imap commands, including custom commands, search etc.
 * Class Imap
 */
class Imap
{
    private $commandCounter = "00000001";
    private $fp;
    public $error;

    public $lastResponse = array();
    public $lastEndline = "";
    private $fullDebug = false;

    public function init($host, $port)
    {
        if (!($this->fp = fsockopen($host, $port, $errno, $errstr, 15))) {
            $this->error = "Could not connect to host ($errno) $errstr";

            return false;
        }

        if (!stream_set_timeout($this->fp, 15)) {
            $this->error = "Could not set timeout";

            return false;
        }

        $line = fgets($this->fp);
        if ($this->fullDebug) {
            echo $line;
        }

        return true;
    }

    public function login($login, $pwd)
    {
        $this->command("LOGIN $login $pwd");

        if (preg_match('~^OK~', $this->lastEndline)) {

            return true;
        } else {
            $this->error = join(', ', $this->lastResponse);
            $this->close();

            return false;
        }
    }

    public function selectFolder($folder)
    {
        $this->command("SELECT $folder");

        if (preg_match('~^OK~', $this->lastEndline)) {
            return true;
        } else {
            $this->error = join(', ', $this->lastResponse);
            $this->close();

            return false;
        }
    }

    public function search($criteria)
    {
        $this->command("SEARCH $criteria");
        if (preg_match('~^OK~', $this->lastEndline) && is_array($this->lastResponse) && count($this->lastResponse) == 1) {
            $splitted_response = explode(' ', $this->lastResponse[0]);
            $uids              = array();

            foreach ($splitted_response as $item) {
                if (preg_match('~^\d+$~', $item)) {
                    $uids[] = $item;
                }
            }

            return $uids;
        } else {
            $this->error = join(', ', $this->lastResponse);
            $this->close();

            return false;
        }
    }

    public function getHeadersFromUid($uid)
    {
        $this->command("FETCH $uid BODY.PEEK[HEADER]");

        if (preg_match('~^OK~', $this->lastEndline)) {
            array_shift($this->lastResponse); // skip first line

            $headers    = array();
            $prev_match = '';
            foreach ($this->lastResponse as $item) {
                if (preg_match('~^([a-z][a-z0-9-_]+):~is', $item, $match)) {
                    $header_name           = strtolower($match[1]);
                    $prev_match            = $header_name;
                    $headers[$header_name] = trim(substr($item, strlen($header_name) + 1));
                } else {
                    $headers[$prev_match] .= " " . $item;
                }
            }

            return $headers;
        } else {
            $this->error = join(', ', $this->lastResponse);
            $this->close();

            return false;
        }
    }

    private function command($command)
    {
        $this->lastResponse = array();
        $this->lastEndline  = "";

        if ($this->fullDebug) {
            echo "$this->commandCounter $command\r\n";
        }

        fwrite($this->fp, "$this->commandCounter $command\r\n");

        while ($line = fgets($this->fp)) {
            $line = trim($line); // do not combine with the line above in while loop, because sometimes valid response maybe \n

            if ($this->fullDebug) {
                echo "Line: [$line]\n";
            }

            $line_arr = preg_split('/\s+/', $line, 0, PREG_SPLIT_NO_EMPTY);
            if (count($line_arr) > 0) {
                $code = array_shift($line_arr);
                if (strtoupper($code) == $this->commandCounter) {
                    $this->lastEndline = join(' ', $line_arr);
                    break;
                } else {
                    $this->lastResponse[] = $line;
                }
            } else {
                $this->lastResponse[] = $line;
            }
        }

        $this->incrementCounter();
    }

    private function incrementCounter()
    {
        $this->commandCounter = sprintf('%08d', intval($this->commandCounter) + 1);
    }

    public function close()
    {
        fclose($this->fp);
    }
}