<?php
/**
 * Created by PhpStorm.
 * User: Igor
 * Date: 03.01.2018
 * Time: 0:55
 */

$errors = array();

if (isset($_POST['min']) &&
    isset($_POST['max']) &&
    isset($_POST['quantity']) &&
    isset($_POST['quantity_part']) &&
    isset($_POST['curr_part']) &&
    isset($_POST['parts'])
) {

    if ($_POST['curr_part'] === '1') {
        if (file_exists("data_cache")) {
            unlink("data_cache");
        }
        if (file_exists("data_statistics")) {
            unlink("data_statistics");
        }
        if (file_exists("data_group")) {
            unlink("data_group");
        }
    }

    if ($_POST['min'] > PHP_INT_MAX || $_POST['max'] > PHP_INT_MAX) {
        $errors[] = 'PHP_INT_MAX error';
    }

    if (count($errors) === 0) {

        if (!file_exists("data_statistics")) {
            // Get medians positions
            $m_positions = array();
            if ($_POST['quantity'] % 2 === 0) {
                $m_positions[] = $_POST['quantity'] / 2 - 1;
                $m_positions[] = ($_POST['quantity'] / 2);
            } else {
                $m_positions[] = round(($_POST['quantity'] / 2), 0, PHP_ROUND_HALF_DOWN);
            }

            $stream_statistics = new FileWriter('data_statistics', 'w');
            $stream_statistics->WriteDataStatistics([], null, null, $m_positions, null, null);
            unset($stream_statistics);
        }

        $i = 0;
        $numbers = array();
        $group = array();
        if (file_exists("data_group")) {
            $stream_group = new FileReader('data_group');
            $stream_group->SetOffset(0);
            $group = json_decode($stream_group->Read(1)[0], true);
        }

        while ($i < $_POST['quantity_part']) {

            $numbers[$i] = mt_rand($_POST['min'], $_POST['max']);

            // Get groups for moda
            $group[$numbers[$i]] = $group[$numbers[$i]] + 1;

            $stream_statistics = new FileReader('data_statistics');
            $stream_statistics->SetOffset(0);
            $statistics = json_decode($stream_statistics->Read(1)[0], true);

            // Get current (real) position
            $c_position = $statistics['c_position'];
            if ($c_position === null) {
                $c_position = 0;
            } else {
                $c_position = $c_position + 1;
            }

            // Get number values for mediana
            $m_positions = $statistics['m_positions'];
            $m_values = $statistics['m_values'];
            if (in_array($c_position, $m_positions)) {
                $m_values[] = $numbers[$i];
            }
            unset($stream_statistics);

            $stream_statistics = new FileWriter('data_statistics', 'r+');
            $stream_statistics->WriteDataStatistics([], null, null, null, $c_position, $m_values);
            unset($stream_statistics);

            $i = $i + 1;
        }

        $stream_group = new FileWriter('data_group', 'w');
        $stream_group->WriteDataGroup($group);
        unset($stream_group);
        unset($group);

        $stream_cache = new FileWriter('data_cache', 'a');
        $stream_cache->WriteDataCache($numbers);
        unset($stream_cache);

        $stream_statistics = new FileWriter('data_statistics', 'r+');
        $stream_statistics->WriteDataStatistics($numbers, $_POST['quantity_part'], null, null, null, null);

        unset($numbers);
        unset($stream_statistics);

        $returnData = array(
            'status' => 'cached ' . $_POST['curr_part'] . '/' . $_POST['parts']
        );

        // Last pass
        if ($_POST['curr_part'] === $_POST['parts']) {

            // Average calculate
           $stream_statistics = new FileReader('data_statistics');
           $stream_statistics->SetOffset(0);
           $statistics = json_decode($stream_statistics->Read(1)[0], true);
           $average = round($statistics['sum'] / $statistics['quantity'], 0);
           $returnData['average'] = $average;
           unset($stream_statistics);

            // Deviation calculate
            $n = 0;
            while (true) {
                $stream_cache = new FileReader('data_cache');
                $stream_cache->SetOffset($n);
                $numbers_line = $stream_cache->Read(1);
                unset($stream_cache);

                $numbers = explode(',', $numbers_line[0]);

                for ($i = 0; $i < count($numbers); $i++) {
                    if ($numbers[$i] === '') {
                        unset($numbers);
                        break 2;
                    }
                    $difference = $numbers[$i] - $average;
                    $deviation_summand = $difference * $difference;
                    $stream_statistics = new FileWriter('data_statistics', 'r+');
                    $stream_statistics->WriteDataStatistics([], null, round($deviation_summand, 2), null, null, null);
                    unset($stream_statistics);
                }

                unset($numbers);

                $n = $n + 1;
            }
            $stream_statistics = new FileReader('data_statistics');
            $stream_statistics->SetOffset(0);
            $statistics = json_decode($stream_statistics->Read(1)[0], true);
            $deviation = round(sqrt($statistics['deviation_sum'] / ($statistics['quantity'] - 1)), 2);
            $returnData['deviation'] = $deviation;

            // Mediana calculate
            $m_values = $statistics['m_values'];
            if (count($m_values) === 1) {
                $mediana = $m_values[0];
            } else {
                $mediana = round(($m_values[0] + $m_values[1]) / 2, 2);
            }
            $returnData['mediana'] = $mediana;
            unset($stream_statistics);

            // Moda calculate
            $stream_group = new FileReader('data_group');
            $stream_group->SetOffset(0);
            $group = json_decode($stream_group->Read(1)[0], true);
            unset($stream_group);
            $moda = array();
            $c = 1;
            foreach ($group as $key => $value) {
                if ($value > 1) {
                    if ($value > $c) {
                        $moda = array();
                        $moda[] = $key;
                        $c = $value;
                    } else if ($value === $c) {
                        $moda[] = $key;
                    }
                }
            }
            $returnData['moda'] = $moda;
            unset($group);

            $returnData['peak_requested'] = (int) (memory_get_peak_usage() / 1024) . ' KB';
            $returnData['peak_allocated'] = (int) (memory_get_peak_usage(true) / 1024) . ' KB';

        } else {
            $returnData['peak_requested'] = (int) (memory_get_peak_usage() / 1024) . ' KB';
            $returnData['peak_allocated'] = (int) (memory_get_peak_usage(true) / 1024) . ' KB';
        }

        echo json_encode($returnData);

    } else {
        returnErrors($errors);
    }

}

/**
 * Return errors
 * @param $errors
 */
function returnErrors($errors) {
    echo json_encode(array(
            'errors' => $errors,
            'peak_requested' => (int) (memory_get_peak_usage() / 1024) . ' KB',
            'peak_allocated' => (int) (memory_get_peak_usage(true) / 1024) . ' KB'
        )
    );
}

/**
 * Helper-function for to control memory
 */
function echoMemoryUsage() {
    echo 'Requested: ' . (int) (memory_get_usage() / 1024) . ' KB';
    echo PHP_EOL . '';
    echo 'Allocated: ' . (int) (memory_get_usage(true) / 1024) . ' KB';
    echo PHP_EOL . PHP_EOL;
}

/**
 *  Helper-function for to control memory peak
 */
function echoMemoryPeakUsage() {
    echo 'Peak requested: ' . (int) (memory_get_peak_usage() / 1024) . ' KB';
    echo PHP_EOL;
    echo 'Peak allocated: ' . (int) (memory_get_peak_usage(true) / 1024) . ' KB';
}

class FileWriter {

    protected $handler = null;
    protected $filename = null;

    public function __construct($filename, $mode) {
        $this->handler = fopen($filename, $mode);
        $this->filename = $filename;
    }

    /**
     * Write data cache in data_cache file
     * @param $numbers
     */
    public function WriteDataCache($numbers) {
        $str = implode(',', $numbers) . "\n";
        fputs($this->handler, $str);
    }

    /**
     * Write data statistics in data_statistics file
     * @param $numbers
     * @param $quantity
     * @param int $deviation_summand
     * @param null $m_positions
     * @param null $c_position
     * @param null $m_values
     */
    public function WriteDataStatistics($numbers = [], $quantity = null, $deviation_summand = null, $m_positions = null, $c_position = null, $m_values = null) {

        $stream_statistics = new FileReader('data_statistics');
        $stream_statistics->SetOffset(0);
        $statistics = json_decode($stream_statistics->Read(1)[0], true);
        unset($stream_statistics);
       // $str = file_get_contents($this->filename);
        //$data = json_decode($str, true);

        if (count($numbers) > 0) {
            $sum = array_sum($numbers);
            $statistics['sum'] = $statistics['sum'] + $sum;
        }
        if ($quantity !== null) {
            $statistics['quantity'] = $statistics['quantity'] + $quantity;
        }
        if ($deviation_summand !== null) {
            $statistics['deviation_sum'] = $statistics['deviation_sum'] + $deviation_summand;
        }
        if ($m_positions !== null) {
            $statistics['m_positions'] = $m_positions;
        }
        if ($c_position !== null) {
            $statistics['c_position'] = $c_position;
        }
        if ($m_values !== null) {
            $statistics['m_values'] = $m_values;
        }
        fputs($this->handler, json_encode($statistics));
    }

    /**
     * Write group
     * @param $group
     */
    public function WriteDataGroup($group) {
        fputs($this->handler, json_encode($group));
    }

    public function __destruct() {
        fclose($this->handler);
    }

}

class FileReader {

    protected $handler = null;
    protected $f_buffer = array();

    public function __construct($filename) {
        $this->handler = fopen($filename, "rb");
    }

    public function SetOffset($line = 0) {
        while (!feof($this->handler) && $line--) {
            fgets($this->handler);
        }
    }

    public function Read($count_line = 1) {
        while (!feof($this->handler)) {
            $this->f_buffer[] = str_replace(array("\r", "\n"), "", fgets($this->handler));
            $count_line--;
            if ($count_line == 0) break;
        }
        return $this->f_buffer;
    }

    public function __destruct() {
        fclose($this->handler);
    }
}


