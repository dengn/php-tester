package mo.mybatis;

import org.apache.ibatis.annotations.Select;

import java.util.List;
import java.util.Map;

/** A generic mapper that reads back rows from the per-type test table. */
public interface GenericMapper {
    @Select("SELECT id, c FROM mb_typetest ORDER BY id")
    List<Map<String, Object>> readAll();

    @Select("SELECT c FROM mb_typetest WHERE id = #{id}")
    Object readOne(int id);

    @Select("SELECT COUNT(*) FROM mb_typetest")
    long count();

    @Select("SELECT id, c FROM mb_typetest WHERE c IS NOT NULL ORDER BY id")
    List<Map<String, Object>> readNotNull();

    @Select("SELECT id FROM mb_typetest ORDER BY id DESC")
    List<Integer> readIdsDesc();

    @Select("SELECT MAX(id) FROM mb_typetest")
    Integer maxId();

    @org.apache.ibatis.annotations.Delete("DELETE FROM mb_typetest WHERE id = #{id}")
    int deleteById(int id);

    @org.apache.ibatis.annotations.Update("UPDATE mb_typetest SET id = id WHERE id = #{id}")
    int touch(int id);

    @Select("SELECT c FROM mb_typetest GROUP BY c")
    List<Object> groupByC();
}
