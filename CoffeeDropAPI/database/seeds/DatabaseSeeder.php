<?php

use Illuminate\Database\Seeder;
use League\Csv\Reader;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $csv = Reader::createFromPath(storage_path('location_data.csv'), 'r');
        $csv->setHeaderOffset(0); //set the CSV header offset

        foreach ($csv as $record) {
            DB::table('locations')->insert([
                'postcode'          => $record['postcode'],
                'open_Monday'       => $record['open_Monday'],
                'open_Tuesday'      => $record['open_Tuesday'],
                'open_Wednesday'    => $record['open_Wednesday'],
                'open_Thursday'     => $record['open_Thursday'],
                'open_Friday'       => $record['open_Friday'],
                'open_Saturday'     => $record['open_Saturday'],
                'open_Sunday'       => $record['open_Sunday'],
                'closed_Monday'     => $record['closed_Monday'],
                'closed_Tuesday'    => $record['closed_Tuesday'],
                'closed_Wednesday'  => $record['closed_Wednesday'],
                'closed_Thursday'   => $record['closed_Thursday'],
                'closed_Friday'     => $record['closed_Friday'],
                'closed_Saturday'   => $record['closed_Saturday'],
                'closed_Sunday'     => $record['closed_Sunday']
            ]);
        }
    }
}
