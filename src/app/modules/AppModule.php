<?php
namespace app\modules;

use Exception;
use php\compress\ZipFile;
use std, framework, app;


class AppModule extends AbstractModule
{
    function __construct(){
        new Thread(function(){
            while(!Application::isCreated());
            
            print "JDecompiler 1.0\n\n";

            while(!isset($file)){
                print "Type path to the file to need decompile.\n";
                print "Path: ";

                (new MiscStream('stdin')->eachLine(function($line)use(&$file){
                    $line = trim($line);
                    if( ! empty( $line ) ){
                        if( file_exists($line) )
                            return $file = $line;
                        else 
                            print "File not found! Type path to the file to need decompile.\nPath: ";   
                    } else     
                        return true;
                }, 'cp866'));
            }
            
            $zip = new ZipFile($file);
            
            new Thread(function()use($zip, $file){
                try{
                    $dirname = fs::nameNoExt($file) . "\\";
                    
                    print "Preparing workspace...\n";
                    
                    if( file_exists($dirname) && is_dir($dirname))
                        rmdir($dirname);
                        
                    mkdir($dirname);    
                        
                    $files = $zip->statAll();
                    $count = count($files);
                    $unpacked = 0;
   
                    print "Unpacking ".fs::name($file)."...\n";
  
                    $zip->unpack( $dirname, null, function($name)use(&$unpacked, &$files, &$bundles, &$classes, &$styles){
                        $unpacked++;
                    
                        $file = $files[$name];
                        $exp = explode('/', $name);
                            
                        if( $file['directory'] ){                                
                            if( $exp[0] === 'bundle' && count($exp) == 3 ){
                                $bundles[] = $exp[1];
                                print "-> (BUNDLE) {$exp[1]}\n";
                            }
                        }elseif( $exp[0] === 'app' ){
                            if(fs::ext($name) == 'phb'){
                                $classes[] = $name;
                                print "-> (CLASS) {$name}\n";
                            }
                        }elseif( fs::ext($name) == 'css' ){
                            $styles[] = $name;
                            print "-> (STYLE) {$name}\n";
                        }    
                    });
                    
                    while( $count > $unpacked );
                    
                    foreach (['bundles', 'classes', 'styles'] as $type){            
                        if(($count = count(${$type}))>0){                                    
                            print strtoupper($type) . " ({$count})\n-> " . implode("\n-> ", ${$type});
                        }
                        print "\n\n";
                    }   
                    
                    $checkDecompiler = function()use($md5){
                        $jar = "fernflower.jar";
                        if(! file_exists($jar) ){
                            print "Extract FernFlower decompiler...\n";
                            copy("res://.data/fernflower.jar", $jar);
                        }
                    };
                    $checkDecompiler();
                    
                    print "Preparing workspace...\n";
                    
                    if( file_exists("decompiled") && is_dir("decompiled") )
                        rmdir("decompiled");
                        
                    mkdir("decompiled");
                    
                    if( file_exists("bin") && is_dir("bin") )
                        rmdir("bin");
                        
                    mkdir("bin");
                    
                    print "Decompiling classes...\n";
                    
                    foreach ($classes as $file){
                        $path = ".\\" . $dirname . str_replace("/", "\\", $file);
                        $exists = file_exists($path);
                        print "Decompiling {$file}...".["NOT EXISTS!",""][(int)$exists];
                        
                        if( $exists ){
                            $pathNoExt = fs::parent($file) . "\\" . fs::nameNoExt($file);
                        
                            foreach ( $decompiledPath = explode("\\", "bin\\".$pathNoExt) as $i=>$f )
                                if( ! (file_exists( $decompiledPathDir = implode( "\\", array_slice($decompiledPath, 0, $i+1) ) ) && is_dir($decompiledPathDir)) )
                                    mkdir($decompiledPathDir);
                            
                            foreach ( $decompiledPath = explode("\\", "decompiled\\".$pathNoExt) as $i=>$f )
                                if( ! (file_exists( $decompiledPathDir = implode( "\\", array_slice($decompiledPath, 0, $i+1) ) ) && is_dir($decompiledPathDir)) )
                                    mkdir($decompiledPathDir);
                            
                            $bin = fopen($path, "r");
                            $phb = str::decode( fread( $bin, filesize($path) ), "windows-1251");
                            fclose($bin);
                            
                            $err = 0;
                            $n = 0;
                            
                            while( $kues = strpos($phb, "Кюєѕ", 4) ){
                                $n++;
                                print "\n-> Binary {$n}...";
                                $phb = substr($phb, $kues);  
                                $bin = fopen($classFile = "bin\\".$pathNoExt."\\".fs::nameNoExt($file)."_{$n}.class", "w");
                                fwrite($bin, str::encode( $phb, "windows-1251"));
                                fclose($bin);
                                
                                $checkDecompiler();
                                $output = (new Process(["cmd.exe", "/c", "java", "-jar", "fernflower.jar", str_replace("\\", "/", $classFile), str_replace("\\", "/","decompiled/".$pathNoExt)]))->start()->getInput()->readFully();
                                
                                $fe = file_exists("decompiled\\".$pathNoExt."\\".fs::nameNoExt($file)."_{$n}.java");
                                print ["ERROR", "OK"][(int)$fe];
                                if(!$fe) $err++;
                            }
                            
                            //print PHP_EOL;

                            //print ["OK", "WITH ERRORS ({$err})"][(int)($err>1)];
                        }
                        print str_repeat(PHP_EOL, 2);
                    }
                    
                    print "Decompiling completed!";
                    
                }catch(Exception $ex){
                    print "\n\nError: {$ex->getMessage()} ({$ex->getCode()})";
                }
                
                exit;
            })->start();
            
        })->start();
    }
}