<?php

namespace janole\Laravel\Dockerize\Console\Commands;

use Illuminate\Console\Command;

class DockerBuild extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docker:build {--p|print : Only print the Dockerfile} {--s|save : Only save the Dockerfile}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a Docker Image of this Laravel App';

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
        $this->build(false);
    }

    /**
     * ...
     *
     * @return mixed
     */
    public function build($silent = false)
    {
        $buildpath = ".";

        /* ignore for now ... TODO: make it optional later on ...
        // Copy our own .dockerignore (potentially dangerous!)
        $dockerignore = file_get_contents(base_path("vendor/janole/laravel-dockerize/docker/dockerignore"));
        file_put_contents(base_path(".dockerignore"), $dockerignore);
        */

        //
        if (@strlen(config("dockerize.image")) == 0)
        {
            $this->error("DOCKERIZE_IMAGE missing. Please specify a base name for your docker image.");

            exit(-1);
        }

        //
        $dockerfile = file_get_contents(base_path("vendor/janole/laravel-dockerize/docker/Dockerfile"));

        //
        $dockerfile = str_replace('${DOCKERIZE_BASE_IMAGE}', config("dockerize.base-image"), $dockerfile);

        //
        if (($env = config("dockerize.env")) && file_exists(base_path($env)))
        {
            $dockerfile = str_replace('${DOCKERIZE_ENV}', $env, $dockerfile);
        }
        elseif (($env = ".env") && file_exists(base_path($env)))
        {
            $dockerfile = str_replace('${DOCKERIZE_ENV}', $env, $dockerfile);
        }
        else
        {
            $this->error("Cannot find a proper .env file!");

            exit(-2);
        }

        //
        $imageInfo = static::getImageInfo();

        $dockerfile = str_replace('${DOCKERIZE_VERSION}', $imageInfo["version"], $dockerfile);
        $dockerfile = str_replace('${DOCKERIZE_BRANCH}', $imageInfo["branch"], $dockerfile);

        //
        if ($this->option("print"))
        {
            $this->info($dockerfile);

            return 0;
        }

        //
        $dockerfile = "# Dynamic Dockerfile\n# !!! DO NOT EDIT THIS FILE BY HAND -- YOUR CHANGES WILL BE OVERWRITTEN !!!\n\n$dockerfile";
        @mkdir(base_path($buildpath));
        file_put_contents(base_path("$buildpath/Dockerfile"), $dockerfile);
        
        //
        if ($this->option("save"))
        {
            $this->info("Dockerfile saved to " . base_path("$buildpath/Dockerfile"));

            return 0;
        }

        //
        $cmd = "cd " . base_path() . " && docker build -t " . $imageInfo["image"] . " -f $buildpath/Dockerfile .";
        $this->info($cmd);

        $fd = popen("($cmd) 2>&1", "r");

        while (($line = fgets($fd)) !== FALSE)
        {
            $this->line("* " . trim($line));
        }

        pclose($fd);

        return 0;
    }

    public static function getImageInfo()
    {
        if (@strlen(config("dockerize.image")) == 0)
        {
            return null;
        }

        //
        $IMAGE = config("dockerize.image");

        //
        $VERSION = env("APP_VERSION");

        if (config("dockerize.version") == ":git")
        {
            $BUILD = @exec("git rev-list HEAD --count 2>/dev/null");
        }

        if (strlen($BUILD) > 0)
        {
            $VERSION .= (@strlen($VERSION) > 0 ? "." : "") . $BUILD;
        }

        //
        if (($BRANCH = config("dockerize.branch")) == ":git")
        {
            $BRANCH = @exec("git rev-parse --abbrev-ref HEAD 2>/dev/null");
            $BRANCH = preg_replace("/[^0-9a-z.]/i", "-", $BRANCH);
        }

        //
        if (strlen($VERSION) > 0)
        {
            $IMAGE.= (strpos($IMAGE, ":") !== false ? "-" : ":") . $VERSION;
        }

        if (strlen($BRANCH) > 0)
        {
            $IMAGE.= (strpos($IMAGE, ":") !== false ? "-" : ":") . $BRANCH;
        }

        return ["image" => $IMAGE, "version" => $VERSION, "branch" => $BRANCH];
    }
}
