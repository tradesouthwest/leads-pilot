<?php
declare(strict_types=1);

namespace LeadsPilot;

use RuntimeException;
use PDO;

class Deal
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function getDealsByAgency(int $agencyId): array
    {
        $sql = "SELECT id, agency_id, title, value, stage, notes, created_at
                FROM deals
                WHERE agency_id = :agency_id
                ORDER BY created_at DESC";

        return $this->db->fetchAll($sql, ['agency_id' => $agencyId]);
    }

    public function createDeal(int $agencyId, array $data): int
    {
        $title     = trim($data['title'] ?? '');
        $value     = (float) ($data['value'] ?? 0.0);
        $stage     = $data['stage'] ?? 'lead';
        $notes     = trim($data['notes'] ?? '');

        if (empty($title)) {
            throw new RuntimeException("Lead title is required.");
        }

        $sql = "INSERT INTO deals (agency_id, title, value, stage, notes)
                VALUES (:agency_id, :title, :value, :stage, :notes)";

        $this->db->query($sql, [
            'agency_id' => $agencyId,
            'title'     => $title,
            'value'     => $value,
            'stage'     => $stage,
            'notes'     => $notes,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateStage(int $agencyId, int $dealId, string $stage): bool
    {
        $pdo = $this->db->getConnection();

        $sql = "UPDATE deals 
                SET stage = :stage 
                WHERE id = :id AND agency_id = :agency_id";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':stage'     => $stage,
            ':id'        => $dealId,
            ':agency_id' => $agencyId,
        ]);
    }

    /**
     * Permanently delete a deal scoped to the agency workspace.
     */
    public function deleteDeal(int $agencyId, int $dealId): bool
    {
        $sql = "DELETE FROM deals WHERE id = :id AND agency_id = :agency_id";
        $this->db->query($sql, ['id' => $dealId, 'agency_id' => $agencyId]);
        return true;
    }
}