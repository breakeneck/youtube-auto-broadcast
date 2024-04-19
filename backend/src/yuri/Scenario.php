<?php

namespace App;

use phpseclib3\Net\SSH2;

class Scenario
{
    private $youtube;
    public $camera;
    public $obsPid;
    public function __construct()
    {
//        $this->youtube = new \App\Youtube($_ENV['YOUTUBE_AUTH_FILE']);
//        $this->camera = new \App\Hikvision($_ENV['HIK_HOST'], $_ENV['HIK_USERNAME'], $_ENV['HIK_PASSWORD']);
    }

    public function startBroadcast($title, $minutes)
    {
        $startTime = date('Y-m-d\TH:i:s\Z');
        $endTime = date('Y-m-d\TH:i:s\Z', strtotime("+ $minutes minutes"));
        $broadcastId = $this->youtube->createBroadcast($title, $startTime, $endTime, $_ENV['YOUTUBE_PRIVACY']);

        $this->youtube->bindToStream($broadcastId, $_ENV['YOUTUBE_STREAM_ID']);

        $this->youtube->goLive($broadcastId);

        return $broadcastId;
    }

    public function finishBroadcast($broadcastId)
    {
        $this->youtube->finish($broadcastId);
    }

    public function notify($broadcastId)
    {
        $message = "https://www.youtube.com/watch?v=$broadcastId";
        (new \App\Telegram($_ENV['TG_API_TOKEN']))->message($_ENV['TG_CHAT_ID'], $message);
    }

    public function wait($minutes)
    {
        sleep($minutes * 60);
    }

    public function startObs()
    {
//        $session = ssh2_connect('192.168.88.53', 8349);
//        ssh2_auth_password($session, 'temple', 'prabhupad@');
//
////        $stream = ssh2_exec($session, 'ls -la');
//        $stream = ssh2_exec($session, 'export DISPLAY=:0; nohup obs --startstreaming --disable-shutdown-check');
//        $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
//
//// Enable blocking for both streams
//        stream_set_blocking($errorStream, true);
//        stream_set_blocking($stream, true);
//
//// Whichever of the two below commands is listed first will receive its appropriate output.  The second command receives nothing
//        echo "Output: " . stream_get_contents($stream);
//        echo "Error: " . stream_get_contents($errorStream);
//
//// Close the streams
//        fclose($errorStream);
//        fclose($stream);

        $ssh = $this->loginSSH();
        $ssh->exec('systemctl --user start obs-start');
//        echo $ssh->cmd('export DISPLAY=:0; nohup obs --startstreaming --disable-shutdown-check', true);
    }

    public function stopObs()
    {
        $ssh = $this->loginSSH();
        $ssh->exec('systemctl --user start obs-stop');
    }

    private function loginSSH()
    {
        $ssh = new SSH2('192.168.88.53', 8349);
        if (!$ssh->login('temple', 'prabhupad@')) {
            throw new \Exception('Login failed');
        }
        return $ssh;
    }
}

class Components_Ssh {

    private $host;

    private $user;

    private $pass;

    private $port;

    private $conn = false;

    private $error;

    private $stream;

    private $stream_timeout = 100;

    private $log;

    private $lastLog;

    public function __construct ( $host, $user, $pass, $port ) {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->port = $port;

        if ( $this->connect ()->authenticate () ) {
            return true;
        }
    }

    public function logAction($param)
    {

    }

    public function isConnected () {
        return ( boolean ) $this->conn;
    }

    public function __get ( $name ) {
        return $this->$name;
    }

    public function connect () {
        $this->logAction ( "Connecting to {$this->host}" );
        if ( $this->conn = ssh2_connect ( $this->host, $this->port ) ) {
            return $this;
        }
        $this->logAction ( "Connection to {$this->host} failed" );
        throw new Exception ( "Unable to connect to {$this->host}" );
    }

    public function authenticate () {
        $this->logAction ( "Authenticating to {$this->host}" );
        if ( ssh2_auth_password ( $this->conn, $this->user, $this->pass ) ) {
            return $this;
        }
        $this->logAction ( "Authentication to {$this->host} failed" );
        throw new Exception ( "Unable to authenticate to {$this->host}" );
    }

    public function sendFile ( $localFile, $remoteFile, $permision = 0644 ) {
        if ( ! is_file ( $localFile ) ) throw new Exception ( "Local file {$localFile} does not exist" );
        $this->logAction ( "Sending file $localFile as $remoteFile" );

        $sftp = ssh2_sftp ( $this->conn );
        $sftpStream = @fopen ( 'ssh2.sftp://' . $sftp . $remoteFile, 'w' );
        if ( ! $sftpStream ) {
            //  if 1 method failes try the other one
            if ( ! @ssh2_scp_send ( $this->conn, $localFile, $remoteFile, $permision ) ) {
                throw new Exception ( "Could not open remote file: $remoteFile" );
            }
            else {
                return true;
            }
        }

        $data_to_send = @file_get_contents ( $localFile );

        if ( @fwrite ( $sftpStream, $data_to_send ) === false ) {
            throw new Exception ( "Could not send data from file: $localFile." );
        }

        fclose ( $sftpStream );

        $this->logAction ( "Sending file $localFile as $remoteFile succeeded" );
        return true;
    }

    public function getFile ( $remoteFile, $localFile ) {
        $this->logAction ( "Receiving file $remoteFile as $localFile" );
        if ( ssh2_scp_recv ( $this->conn, $remoteFile, $localFile ) ) {
            return true;
        }
        $this->logAction ( "Receiving file $remoteFile as $localFile failed" );
        throw new Exception ( "Unable to get file to {$remoteFile}" );
    }

    public function cmd ( $cmd, $returnOutput = false ) {
        $this->logAction ( "Executing command $cmd" );
        $this->stream = ssh2_exec ( $this->conn, $cmd );

        if ( FALSE === $this->stream ) {
            $this->logAction ( "Unable to execute command $cmd" );
            throw new Exception ( "Unable to execute command '$cmd'" );
        }
        $this->logAction ( "$cmd was executed" );

        stream_set_blocking ( $this->stream, true );
        stream_set_timeout ( $this->stream, $this->stream_timeout );
        $this->lastLog = stream_get_contents ( $this->stream );

        $this->logAction ( "$cmd output: {$this->lastLog}" );
        fclose ( $this->stream );
        $this->log .= $this->lastLog . "\n";
        return ( $returnOutput ) ? $this->lastLog : $this;
    }

    public function shellCmd ( $cmds = array () ) {
        $this->logAction ( "Openning ssh2 shell" );
        $this->shellStream = ssh2_shell ( $this->conn );

        sleep ( 1 );
        $out = '';
        while ( $line = fgets ( $this->shellStream ) ) {
            $out .= $line;
        }

        $this->logAction ( "ssh2 shell output: $out" );

        foreach ( $cmds as $cmd ) {
            $out = '';
            $this->logAction ( "Writing ssh2 shell command: $cmd" );
            fwrite ( $this->shellStream, "$cmd" . PHP_EOL );
            sleep ( 1 );
            while ( $line = fgets ( $this->shellStream ) ) {
                $out .= $line;
                sleep ( 1 );
            }
            $this->logAction ( "ssh2 shell command $cmd output: $out" );
        }

        $this->logAction ( "Closing shell stream" );
        fclose ( $this->shellStream );
    }

    public function getLastOutput () {
        return $this->lastLog;
    }

    public function getOutput () {
        return $this->log;
    }

    public function disconnect () {
        $this->logAction ( "Disconnecting from {$this->host}" );
        // if disconnect function is available call it..
        if ( function_exists ( 'ssh2_disconnect' ) ) {
            ssh2_disconnect ( $this->conn );
        }
        else { // if no disconnect func is available, close conn, unset var
            @fclose ( $this->conn );
            $this->conn = false;
        }
        // return null always
        return NULL;
    }

    public function fileExists ( $path ) {
        $output = $this->cmd ( "[ -f $path ] && echo 1 || echo 0", true );
        return ( bool ) trim ( $output );
    }
}
