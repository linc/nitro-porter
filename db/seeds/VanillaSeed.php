<?php


use Phinx\Seed\AbstractSeed;

class VanillaSeed extends AbstractSeed
{
    public function getDependencies()
    {
        return [
            // 'UserSeeder',
        ];
    }

    /**
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run()
    {
        $data = [
            [
                'Name' => 'Linc',
                'Email' => 'lincoln@icrontic.com',
                'Password' => 'admin',
            ]
        ];

        $posts = $this->table('GDN_User');
        $posts->insert($data)
              ->saveData();
    }
}
