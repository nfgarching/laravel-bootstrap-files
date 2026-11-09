<?php

namespace Nfgarching\Files\Services;

use Nfgarching\Files\Models\File;

class PathGenerator
{
    public string $prefix = '';

    public string $suffix = '';

    public string $postfix = '';

    public function __construct()
    {
        $this->prefix = config('files.file_dir_prefix', '');
        $this->suffix = config('files.file_dir_suffix', '');
        $this->postfix = config('files.file_dir_postfix', '');
    }

    /*
     * Get the path for the given file, relative to the root storage path.
     */
    public function getPath(File $fileitem): string
    {
        return ($this->prefix ? '/'.$this->prefix : '').
                '/'.date('Y', strtotime($fileitem->created_at)).
               ($this->suffix ? '/'.$this->suffix : '').
                '/files'.
                '/'.date('Y-m', strtotime($fileitem->created_at)).
               ($this->postfix ? '/'.$this->postfix : '');
        // $fileitem->id.'';
    }

    /*
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForConversions(File $fileitem): string
    {
        return ($this->prefix ? '/'.$this->prefix : '').
                '/'.date('Y', strtotime($fileitem->created_at)).
               ($this->suffix ? '/'.$this->suffix : '').
                '/convs'.
                '/'.date('Y-m', strtotime($fileitem->created_at)).
               ($this->postfix ? '/'.$this->postfix : '');
        // $fileitem->id.'';
        // return ($this->prefix ? $this->prefix.'/' : '').
        //         date('Y', strtotime($fileitem->created_at)).'/'.
        //        ($this->suffix ? $this->suffix.'/' : '').
        //         'convs/'.
        //         date('Y-m', strtotime($fileitem->created_at)).'/'.
        //        ($this->postfix ? $this->postfix.'/' : '').
        //         $fileitem->id.'';
    }

    /*
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForOcr(File $fileitem): string
    {
        return ($this->prefix ? '/'.$this->prefix : '').
                '/'.date('Y', strtotime($fileitem->created_at)).
               ($this->suffix ? '/'.$this->suffix : '').
                '/ocrs'.
                '/'.date('Y-m', strtotime($fileitem->created_at)).
               ($this->postfix ? '/'.$this->postfix : '');
        // $fileitem->id.'';
        // return ($this->prefix ? $this->prefix.'/' : '').
        //         date('Y', strtotime($fileitem->created_at)).'/'.
        //        ($this->suffix ? $this->suffix.'/' : '').
        //         'ocrs/'.
        //         date('Y-m', strtotime($fileitem->created_at)).'/'.
        //        ($this->postfix ? $this->postfix.'/' : '').
        //         $fileitem->id.'';
    }

    /*
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(File $fileitem): string
    {
        return ($this->prefix ? '/'.$this->prefix : '').
                '/'.date('Y', strtotime($fileitem->created_at)).
               ($this->suffix ? '/'.$this->suffix : '').
                '/respos'.
                '/'.date('Y-m', strtotime($fileitem->created_at)).
               ($this->postfix ? '/'.$this->postfix : '');
        // $fileitem->id.'';
        // return ($this->prefix ? $this->prefix.'/' : '').
        //         date('Y', strtotime($fileitem->created_at)).'/'.
        //        ($this->suffix ? $this->suffix.'/' : '').
        //         'respos/'.
        //         date('Y-m', strtotime($fileitem->created_at)).'/'.
        //        ($this->postfix ? $this->postfix.'/' : '').
        //         $fileitem->id.'';
    }

    /*
     * Get the path for the archive version (PDF/A, sealed) of the given file.
     */
    public function getPathForArchive(File $fileitem): string
    {
        return ($this->prefix ? '/'.$this->prefix : '').
                '/'.date('Y', strtotime($fileitem->created_at)).
               ($this->suffix ? '/'.$this->suffix : '').
                '/archive'.
                '/'.date('Y-m', strtotime($fileitem->created_at)).
               ($this->postfix ? '/'.$this->postfix : '');
    }

    /*
     * Get the trash path for originals replaced by their archive version.
     */
    public function getPathForTrash(File $fileitem): string
    {
        return ($this->prefix ? '/'.$this->prefix : '').
                '/'.date('Y', strtotime($fileitem->created_at)).
               ($this->suffix ? '/'.$this->suffix : '').
                '/trash'.
                '/'.date('Y-m', strtotime($fileitem->created_at)).
               ($this->postfix ? '/'.$this->postfix : '');
    }
}
