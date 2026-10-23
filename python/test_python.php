<?php

$python = '/home/aplb6542/virtualenv/toko_sabrina_python/3.12/bin/python';

echo '<pre>';

echo "PHP Version: " . PHP_VERSION . "\n";
echo "Python: " . $python . "\n\n";

echo "Cek Python:\n";
echo shell_exec(escapeshellarg($python) . ' --version 2>&1');

echo "\nCek paket:\n";

$kode = 'import pandas, statsmodels, xgboost, sklearn; print("SEMUA PAKET OK")';

$cmd = 'OPENBLAS_NUM_THREADS=1 OMP_NUM_THREADS=1 MKL_NUM_THREADS=1 '
     . escapeshellarg($python)
     . ' -c '
     . escapeshellarg($kode)
     . ' 2>&1';

echo shell_exec($cmd);

echo '</pre>';