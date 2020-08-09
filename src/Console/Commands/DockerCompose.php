<?php

namespace janole\Laravel\Dockerize\Console\Commands;

use Illuminate\Console\Command;

class DockerCompose extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docker:compose {--p|print : Only print the docker-compose-yml only} {--s|save : Only save the docker-compose.yml}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Laravel App via Docker';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->compose();
    }

    public function compose()
    {
        DockerBuild::loadConfig();

        //
        $imageInfo = DockerBuild::getImageInfo();
        $IMAGE = $imageInfo["image"];

        //
        $DB =
        [
            "DB_HOST" => "database",
            "DB_PORT" => "5432",
            "DB_DATABASE" => "database",
            "DB_USERNAME" => "postgres",
            "DB_PASSWORD" => "0secret0",
        ];

        //
        $volumes = [];

        //
        $app = [];

        $app["image"] = $IMAGE;
        $app["ports"] = ["0.0.0.0:" . env("DOCKERIZE_PORT", 3333) . ":" . env("DOCKERIZE_CONTAINER_PORT", 80)];

        if (($appVolumes = json_decode(env("DOCKERIZE_SHARE"), true)))
        {
            $app["volumes"] = $appVolumes;
        }

        $env = [];
        $env["APP_URL"] = "http://" . env("DOCKERIZE_HOST", "localhost") . ":" . env("DOCKERIZE_PORT", 3333);
        $env["APP_ENV"] = env("APP_ENV", "production");

        foreach ($DB as $key => $val)
        {
            $env[$key] = $val;
        }

        foreach ($_ENV as $key => $val)
        {
            if (strpos($key, "DOCKERIZE_COMPOSE_ENV_") === 0)
            {
                $env[substr($key, strlen("DOCKERIZE_COMPOSE_ENV_"))] = $val;
            }
        }

        foreach (getenv() as $key => $val)
        {
            if (strpos($key, "DOCKERIZE_COMPOSE_ENV_") === 0)
            {
                $env[substr($key, strlen("DOCKERIZE_COMPOSE_ENV_"))] = $val;
            }
        }

        $app["environment"] = $env;

        //
        $database =        
        [
            "image" => "postgres",
            "environment" => 
            [
                "POSTGRES_DB" => $DB["DB_DATABASE"],
                "POSTGRES_PASSWORD" => $DB["DB_PASSWORD"]
            ],
            "volumes" =>
            [
                "postgres-data:/var/lib/postgresql/data"
            ]
        ];

        $volumes["postgres-data"] = ["labels" => ["com.janole.laravel-dockerize.description" => "Laravel Database Volume"]];

        //
        if (($BRANCH = env("DOCKERIZE_BRANCH", ":git")) == ":git")
        {
            $BRANCH = exec("git rev-parse --abbrev-ref HEAD");
        }

        //
        $yaml =
        [
            "version" => "3",
            "services" =>
            [
                "app" => $app,
                "database" => $database,
            ],
            "volumes" => $volumes,
        ];

        $dockercompose = static::yamlize($yaml);

        if ($this->option("print"))
        {
            $this->info($dockercompose);

            return 0;
        }

        if ($this->option("save"))
        {
            $file = base_path("docker-compose.yml");

            file_put_contents($file, $dockercompose);

            $this->info("File saved as $file");

            return 0;
        }

        //
        exec("docker inspect --type=image $IMAGE > /dev/null 2>&1", $output, $ret);

        if ($ret != 0)
        {
            $this->warn("Please build $IMAGE first.");
        }

        //
        $dockercompose = "# Dynamic docker-compose.yml\n# !!! DO NOT EDIT THIS FILE BY HAND -- YOUR CHANGES WILL BE OVERWRITTEN !!!\n\n$dockercompose";
        file_put_contents($file = base_path("docker-compose.yml"), $dockercompose);

        $this->info("File saved as $file");
        
        return 0;
    }

    public static function yamlize($array, $indent = 0)
    {
        $yaml = "";

        foreach ($array as $key => $val)
        {
            if (is_array($val))
            {
                $yaml .= str_repeat(" ", $indent) . "$key:\n";

                $yaml .= static::yamlize($val, $indent + 1);
            }
            else
            {
                if (@intval($key) > 0 || $key == "0")
                {
                    $key = "-";
                }
                else
                {
                    $key.= ":";
                }

                $yaml .= str_repeat(" ", $indent) . "$key \"" . str_replace('"', "'", $val) . "\"\n";
            }
        }

        return $yaml;
    }
}
