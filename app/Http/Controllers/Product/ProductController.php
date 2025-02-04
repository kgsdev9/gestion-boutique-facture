<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\TCategorieProduct;
use App\Models\TProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = TProduct::with('category')->orderByDesc('created_at');
        $listecategorie = TCategorieProduct::all();
        $users = $query->get();

        // Ajouter l'URL de l'image à chaque produit
        $users->each(function ($product) {
            // Assurez-vous que l'URL est construite correctement sans doublon
            $product->image_url = $product->image
                ? asset('s3/' . $product->image) // Utilisation correcte du disque local
                : asset('defaultimage.webp');     // Image par défaut
        });

        return view('products.index', [
            'listeproducts' => $users,
            'listecategorie' => $listecategorie,
        ]);
    }





    public function store(Request $request)
    {
        // Vérifier si product_id existe dans la requête
        $productId = $request->input('product_id');

        if ($productId) {
            // Si product_id existe, on modifie le produit
            $product = TProduct::find($productId);

            // Si le produit n'existe pas, le créer
            if (!$product) {
                // Créer un nouveau produit
                return $this->createProduct($request);
            }

            // Si le produit existe, procéder à la mise à jour
            return $this->updateProduct($product, $request);
        } else {
            // Si product_id est absent, on crée un nouveau produit
            return $this->createProduct($request);
        }
    }


    private function createProduct(Request $request)
    {
        // Vérifier que l'image est présente et valide
        if ($request->hasFile('image') && $request->file('image')->isValid()) {

            $image = $request->file('image');

            // Récupérer le nom original du fichier
            $originalName = $image->getClientOriginalName();
            $imagePath = $request->file('image')->storeAs('products', $originalName);
        } else {
            return response()->json(['message' => 'Image invalide ou absente.'], 400);
        }

        // Création d'un nouveau produit
        $product = TProduct::create([
            'libelleproduct' => $request->name,
            'prixachat' => $request->prixachat,
            'qtedisponible' => $request->qtedisponible,
            'prixvente' => $request->prixvente,
            'tcategorieproduct_id' => $request->category_id,
            'image' => $imagePath, // Enregistrer le chemin relatif de l'image
        ]);

        // Ajouter l'URL de l'image au produit pour qu'il soit accessible facilement
        $product->image_url = asset('s3/' . $product->image);

        // Charger la catégorie associée
        $product->load('category');

        // Retourner la réponse avec le produit créé
        return response()->json(['message' => 'Produit créé avec succès', 'product' => $product], 201);
    }


    private function updateProduct($product, Request $request)
    {
        // Gérer l'upload de l'image s'il y en a une
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $image = $request->file('image');

            // Récupérer le nom original du fichier
            $originalName = $image->getClientOriginalName();

            // Stocker l'image sous le nom original dans le dossier 'products' sur le disque public
            $imagePath = $request->file('image')->storeAs('products', $originalName);

            // Supprimer l'ancienne image si elle existe
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
        } else {
            // Garder l'ancienne image si aucune nouvelle image n'est téléchargée
            $imagePath = $product->image;
        }

        // Mise à jour du produit
        $product->update([
            'libelleproduct' => $request->name,
            'prixachat' => $request->prixachat,
            'qtedisponible' => $request->qtedisponible,
            'prixvente' => $request->prixvente,
            'tcategorieproduct_id' => $request->category_id,
            'image' => $imagePath,
        ]);

        // Ajouter l'URL de l'image au produit pour qu'il soit accessible facilement
        // Utiliser 'asset' sans 'storage' pour générer l'URL correcte
        $product->image_url = asset('s3/' . $product->image);

        $product->load('category');

        // Retourner la réponse avec le produit mis à jour
        return response()->json(['message' => 'Produit mis à jour avec succès', 'product' => $product], 200);
    }





    // private function createProduct(Request $request)
    // {
    //     // Vérifier que l'image est présente et valide
    //     if ($request->hasFile('image') && $request->file('image')->isValid()) {

    //         $image = $request->file('image');

    //         // Récupère le nom original du fichier
    //         $originalName = $image->getClientOriginalName();

    //         $imagePath = $request->file('image')->storeAs('products', $originalName);
    //     } else {
    //         return response()->json(['message' => 'Image invalide ou absente.'], 400);
    //     }

    //     // Création d'un nouveau produit
    //     $product = TProduct::create([
    //         'libelleproduct' => $request->name,
    //         'prixachat' => $request->prixachat,
    //         'qtedisponible' => $request->qtedisponible,
    //         'prixvente' => $request->prixvente,
    //         'tcategorieproduct_id' => $request->category_id,
    //         'image' => $imagePath,
    //     ]);
    //     $product->load('category');
    //     return response()->json(['message' => 'Produit créé avec succès', 'product' => $product], 201);
    // }

    public function destroy($id)
    {

        try {
            // Rechercher le produit par ID
            $product = TProduct::findOrFail($id);

            // Supprimer le produit
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Produit supprimé avec succès.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Produit introuvable.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du produit.',
            ], 500);
        }
    }
}
