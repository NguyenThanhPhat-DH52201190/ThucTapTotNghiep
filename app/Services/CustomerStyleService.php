<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerStyleService
{
    public function apply(Request $request, bool $ocs = false): void
    {
        $codeKey = $ocs ? 'SNo' : 'style_no';
        $request->validate(['customer_id' => 'required|integer|exists:customer_info,id', $codeKey => 'required|string|max:191']);
        $style = DB::table('customer_styles')->where('customer_id', $request->integer('customer_id'))
            ->where('style_no', trim((string) $request->input($codeKey)))->first();
        if (!$style) throw ValidationException::withMessages([
            $codeKey => 'Select a style registered for this customer in Customer Master.',
        ]);
        $request->merge([
            $codeKey => $style->style_no,
            ($ocs ? 'Sname' : 'style_name') => $style->style_name,
            ($ocs ? 'Customer' : 'customer') => DB::table('customer_info')->where('id', $style->customer_id)->value('name'),
        ]);
    }

    // Identifiers passed here are application constants, never request input.
    public static function imageSql(string $table, string $code): string
    {
        return "COALESCE((SELECT cs.image_path FROM customer_styles cs WHERE cs.customer_id = {$table}.customer_id AND cs.style_no = {$table}.{$code}), {$table}.image_path)";
    }

    public function imagePath(string $table, int $id): ?string
    {
        $code = $table === 'ocs' ? 'SNo' : 'style_no';
        return DB::table($table)->where('id', $id)->selectRaw(self::imageSql($table, $code).' as resolved_image')->value('resolved_image');
    }
}
