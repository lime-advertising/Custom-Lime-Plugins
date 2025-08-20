<?php
if (!defined('ABSPATH')) exit;

class Lime_Schema_Utils
{
    /**
     * Allowed LocalBusiness subtypes (filterable).
     */
    public static function allowed_lb_types(): array
    {
        $types = [
            // Core
            'LocalBusiness',
            'ProfessionalService',
            'Store',
            'LodgingBusiness',
            'FoodEstablishment',
            'MedicalBusiness',
            'HomeAndConstructionBusiness',
            'AutomotiveBusiness',
            'HealthAndBeautyBusiness',
            'FinancialService',
            'EntertainmentBusiness',
            // Common concrete subtypes
            'AccountingService', 'Attorney', 'Notary', 'TravelAgency',
            'Hotel', 'Motel', 'Resort', 'BedAndBreakfast', 'Hostel', 'Campground',
            'Restaurant', 'FastFoodRestaurant', 'CafeOrCoffeeShop', 'BarOrPub', 'Brewery', 'Winery', 'Distillery',
            'Dentist', 'MedicalClinic', 'Pharmacy', 'VeterinaryCare',
            'Electrician', 'GeneralContractor', 'HVACBusiness', 'HousePainter', 'Locksmith', 'MovingCompany', 'PestControl', 'Plumber', 'RoofingContractor',
            'AutoRepair', 'AutoDealer', 'AutoPartsStore', 'AutoBodyShop', 'AutoWash', 'GasStation', 'MotorcycleDealer', 'MotorcycleRepair',
            'BeautySalon', 'HairSalon', 'DaySpa', 'NailSalon', 'TattooParlor', 'HealthClub',
            'BankOrCreditUnion', 'AutomatedTeller', 'InsuranceAgency',
            'ArtGallery', 'AmusementPark', 'Casino', 'ComedyClub', 'MovieTheater', 'NightClub',
            'Bakery', 'GroceryStore', 'ConvenienceStore', 'DepartmentStore', 'ClothingStore', 'ShoeStore', 'JewelryStore', 'ToyStore', 'SportingGoodsStore', 'FurnitureStore', 'HomeGoodsStore', 'HardwareStore', 'GardenStore', 'PetStore', 'ElectronicsStore', 'ComputerStore', 'MobilePhoneStore', 'BookStore', 'MusicStore', 'OfficeEquipmentStore', 'OutletStore', 'PawnShop', 'TireShop', 'Florist', 'BicycleStore',
        ];
        return apply_filters('lime_schema_allowed_lb_types', $types);
    }
}

