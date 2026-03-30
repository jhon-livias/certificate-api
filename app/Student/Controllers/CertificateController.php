<?php

namespace App\Student\Controllers;

use App\Shared\Foundation\Controllers\Controller;
use App\Student\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function index()
    {
        $templates = Certificate::orderBy('creation_time', 'desc')->get()->map(function ($tpl) {
            return [
                'id' => $tpl->id,
                'name' => $tpl->name,
                'code' => $tpl->code,
                'fileName' => $tpl->file_name,
                'updatedAt' => $tpl->last_modification_time ? $tpl->last_modification_time->diffForHumans() : 'Hace un momento'
            ];
        });

        return response()->json($templates);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'document' => [
                'required',
                'file',
                'mimes:pdf', // <-- Cambiado de docx a pdf
                'mimetypes:application/pdf' // <-- Mimetype oficial de los PDF
            ]
        ]);

        $file = $request->file('document');
        
        // El archivo se guardará en storage/app/templates
        $path = $file->store('templates');

        $certificate = Certificate::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path
        ]);

        return response()->json([
            'message' => 'Plantilla PDF guardada correctamente.',
            'data' => $certificate
        ], 201);
    }

    public function update(Request $request, Certificate $certificate)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'document' => [
                'nullable', // Sigue siendo nullable porque a veces solo editan el nombre
                'file',
                'mimes:pdf', // <-- Aquí el cambio clave a pdf
                'mimetypes:application/pdf' // <-- El mimetype oficial de los PDF
            ]
        ]);

        $certificate->name = $request->name;
        $certificate->code = strtoupper($request->code);

        if ($request->hasFile('document')) {
            // Borramos la plantilla PDF anterior si existe
            if (Storage::exists($certificate->file_path)) {
                Storage::delete($certificate->file_path);
            }

            // Subimos la nueva
            $file = $request->file('document');
            $certificate->file_path = $file->store('templates');
            $certificate->file_name = $file->getClientOriginalName();
        }

        $certificate->save();

        return response()->json([
            'message' => 'Plantilla PDF actualizada correctamente.',
            'data' => $certificate
        ]);
    }

    public function download(Certificate $certificate)
    {
        if (!Storage::exists($certificate->file_path)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        return Storage::download($certificate->file_path, $certificate->file_name);
    }

    public function destroy(Certificate $certificate)
    {
        try {
            // Guardamos la ruta antes de eliminar el modelo
            $filePath = $certificate->file_path;

            // 1. Intentamos borrar en PostgreSQL PRIMERO
            // Si está amarrado a constancias emitidas, esto fallará y saltará al catch
            // protegiendo así nuestro archivo físico.
            $certificate->delete();

            // 2. Si la BD nos dejó borrarlo, ahora sí eliminamos el archivo físico PDF
            if ($filePath && Storage::exists($filePath)) {
                Storage::delete($filePath);
            }

            // 3. Respondemos éxito a Angular
            return response()->json([
                'message' => 'Plantilla PDF y archivo eliminados correctamente.'
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            // Error 23503 es la violación de llave foránea en PostgreSQL
            if ($e->getCode() == '23503') {
                return response()->json([
                    'message' => 'No se puede eliminar la plantilla porque ya tiene constancias emitidas generadas con ella.'
                ], 409); // 409 Conflict
            }

            // Cualquier otro error de base de datos
            return response()->json([
                'message' => 'Error de base de datos al intentar eliminar la plantilla.'
            ], 500);

        } catch (\Exception $e) {
            // Fallo general
            return response()->json([
                'message' => 'Error inesperado: ' . $e->getMessage()
            ], 500);
        }
    }
}
