<?php

namespace App\Exports;

use App\Album;
use Carbon\Carbon;
use Illuminate\Support\Facades\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AlbumExport implements FromCollection, WithHeadings, WithMapping
{
    public $start_date;
    public $end_date;

    public function __construct($start_date = null, $end_date = null)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = Album::with(['likes', 'plays', 'artist'])->where('id', '<>', null);

        /**
         * Set Date Filter Logic
         */
        if($this->start_date){
            $query->whereDate('created_at', '>=', $this->start_date);
        }
        if($this->end_date){
            $query->whereDate('created_at', '<=', $this->end_date);
        }

        $result = $query->orderBy('created_at', 'desc')->get();

        return $result;
    }

    public function map($album): array
    {
        return [
            $album->title,
            null,
            $album->plays->count(),
            $album->likes->count(),
            Carbon::parse($album->release_date)->format('M d Y'),
            Carbon::parse($album->created_at)->format('M d Y')
        ];
    }

    public function headings(): array
    {
        return [
            'Title', 'Artists', 'Plays', 'Likes','Released At', 'Created At'
        ];
    }
}