<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<pre>";

echo "TEST 1 - PHP\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

echo "TEST 2 - HOME\n";
putenv('HOME=/home/aplb6542');
putenv('USER=aplb6542');

echo "HOME = " . getenv('HOME') . "\n";
echo "USER = " . getenv('USER') . "\n\n";

echo "TEST 3 - shell_exec\n";
echo shell_exec('echo SHELL_EXEC_BERHASIL 2>&1');
echo "\n";

echo "TEST 4 - Python absolut --version\n";

$python = '/home/aplb6542/virtualenv/toko_sabrina_python/3.12/bin/python';

$cmd = 'HOME=/home/aplb6542 '
     . 'OPENBLAS_NUM_THREADS=1 '
     . 'OMP_NUM_THREADS=1 '
     . 'MKL_NUM_THREADS=1 '
     . $python
     . ' --version 2>&1';

echo "Perintah:\n";
echo $cmd . "\n\n";

echo "HASIL PYTHON:\n";

$hasil_python = shell_exec($cmd);

echo $hasil_python . "\n";

echo "TEST 5 - Python import pandas\n";

$cmd2 = 'HOME=/home/aplb6542 '
      . 'OPENBLAS_NUM_THREADS=1 '
      . 'OMP_NUM_THREADS=1 '
      . 'MKL_NUM_THREADS=1 '
      . $python
      . ' -c "import pandas; print(\"PANDAS OK\")" 2>&1';

echo "Menjalankan import pandas...\n\n";

echo "HASIL:\n";

$hasil_pandas = shell_exec($cmd2);

echo $hasil_pandas;

echo "\n</pre>";