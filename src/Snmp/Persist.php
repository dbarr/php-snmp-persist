<?php
namespace Snmp;

class Persist
{
    private $sortedOids = false;

    public function setInput($input=STDIN)
    {
        $this->input = $input;
    }

    public function __construct($base_oid='.1.3.6.1.4.1.2022.1')
    {
        $this->base_oid = $base_oid;
        $this->snmp_actions = array();
        $this->setInput();
        $unit = new Unit();
    }

    private function writeln($text)
    {
        printf("%s\n", $text);
    }

    public function registerOid($oid, $type = null, $func = null)
    {
        $this->snmp_actions[$this->base_oid . '.' . $oid] = array(
            'func' => $func,
            'type' => $type
        );

        $this->sortedOids = $this->sortOids(array_keys($this->snmp_actions));
    }

    public function fork()
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die("Could not fork the snmp");
        } else if ($pid) {
             pcntl_wait($status);
        } else {
            $this->run();
        }
    }

    private function sortOids($oids)
    {
        usort($oids, function ($a, $b){
            $a_s = explode(".", $a);
            $b_s = explode(".", $b);
            $i = 0;

            while (1) {
                if (array_key_exists($i, $a_s) && !array_key_exists($i, $b_s)) {
                    return 1;
                }

                if (!array_key_exists($i, $a_s) && array_key_exists($i, $b_s)) {
                    return -1;
                }

                if (!array_key_exists($i, $a_s) && !array_key_exists($i, $b_s)) {
                    return 0;
                }

                if ($a_s[$i] < $b_s[$i]) {
                    return -1;
                } else if ($a_s[$i] > $b_s[$i]) {
                    return 1;
                }
                $i++;
            }
            return 0;
        });
        return $oids;
    }

    public function run()
    {
        while($cmd = fgets($this->input)){
            $cmd = strtoupper(trim($cmd));
            switch ($cmd){
                case 'PING':
                    $this->ping();
                    break;
                case 'GET':
                    $this->get();
                    break;
                case 'GETNEXT':
                    $this->getNext();
                default:
                    break;
            }
        }
    }

    private function getNext()
    {
        $oid = trim(fgets($this->input));

        if (array_key_exists($oid, $this->snmp_actions) === false) {
            $oids = array_keys($this->snmp_actions);
            $oids[] = $oid;
            $sortedoids = $this->sortOids($oids);
        }
        else {
            $sortedoids = $this->sortedOids;
        }

        $key = array_search($oid, $sortedoids, true);

        if (array_key_exists($key + 1, $sortedoids) === false)
            $this->getOid('ENDOFOIDS');
        else
            $this->getOid($sortedoids[$key + 1]);
    }

    private function get()
    {
        $oid = fgets($this->input);
        $oid = trim($oid);
        $this->getOid($oid);
    }

    private function getOid($oid)
    {
        if(array_key_exists($oid, $this->snmp_actions)){
            $result = '';
            if($func = $this->snmp_actions[$oid]['func']){
                $result = $func();
            } else {
                $this->writeln("NONE");
                return;
            }
            $this->writeln($oid);
            $this->writeln($this->snmp_actions[$oid]['type']);
            $this->writeln($result);
        } else {
            $this->writeln("NONE");
        }
    }

    private function ping()
    {
        $this->writeln('PONG');
    }
}
