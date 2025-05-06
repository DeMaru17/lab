<?php
namespace Database\Factories;
use App\Models\Attendance;
use App\Models\User;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition()
    {
        // Ambil user dan shift secara acak sebagai default
        $user = User::whereIn('role', ['personil', 'admin'])->inRandomOrder()->first() ?? User::factory()->create(['role' => 'personil']);
        $shift = Shift::inRandomOrder()->first() ?? Shift::factory()->create();
        $date = $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d');

        // Default: Hadir
        $clockIn = Carbon::parse($date . ' ' . $shift->start_time->format('H:i:s'))->addMinutes(rand(0, 4));
        $clockOut = Carbon::parse($date . ' ' . $shift->end_time->format('H:i:s'))->addMinutes(rand(0, 30));
         if($shift->crosses_midnight) $clockOut->addDay(); // Sesuaikan jika cross midnight

        return [
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'attendance_date' => $date,
            'clock_in_time' => $clockIn,
            'clock_out_time' => $clockOut,
            'clock_in_latitude' => $this->faker->latitude(-6.1, -6.3),
            'clock_in_longitude' => $this->faker->longitude(106.7, 107.0),
            'clock_in_location_status' => 'Dalam Radius',
            'clock_out_latitude' => $this->faker->latitude(-6.1, -6.3),
            'clock_out_longitude' => $this->faker->longitude(106.7, 107.0),
            'clock_out_location_status' => 'Dalam Radius',
            'attendance_status' => null,
            'notes' => null,
            'is_corrected' => false,
        ];
    }

    // Definisikan state untuk skenario lain
    public function late()
    {
        return $this->state(function (array $attributes) {
            $shift = Shift::find($attributes['shift_id']);
            return [
                'clock_in_time' => Carbon::parse($attributes['attendance_date'] . ' ' . $shift->start_time->format('H:i:s'))->addMinutes(rand(10, 60)),
            ];
        });
    }

    public function earlyLeave()
    {
         return $this->state(function (array $attributes) {
            $shift = Shift::find($attributes['shift_id']);
            $clockOut = Carbon::parse($attributes['attendance_date'] . ' ' . $shift->end_time->format('H:i:s'))->subMinutes(rand(15, 90));
            if($shift->crosses_midnight) $clockOut->addDay(); // Sesuaikan jika cross midnight
            return [
                'clock_out_time' => $clockOut,
            ];
        });
    }

     public function incomplete()
    {
         return $this->state(function (array $attributes) {
            return ['clock_out_time' => null];
        });
    }

     public function weekendOvertime()
     {
         return $this->state(function (array $attributes) {
             $date = Carbon::parse($attributes['attendance_date']);
             // Pastikan tanggal adalah weekend
             while (!$date->isWeekend()) { $date->subDay(); }
             return [
                 'attendance_date' => $date->toDateString(),
                 'clock_in_time' => Carbon::parse($date->toDateString() . ' 08:00:00')->addMinutes(rand(0,15)),
                 'clock_out_time' => Carbon::parse($date->toDateString() . ' 12:00:00')->addMinutes(rand(0,15)),
                 // TODO: Pastikan ada Overtime approved juga
             ];
         });
     }
}
