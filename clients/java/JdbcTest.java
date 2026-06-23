import java.sql.*;
public class JdbcTest {
  public static void main(String[] a) throws Exception {
    try (Connection c = DriverManager.getConnection("jdbc:mysql://127.0.0.1:6001/?allowPublicKeyRetrieval=true&useSSL=false","root","111")) {
      ResultSet rs = c.createStatement().executeQuery("select version()");
      rs.next();
      System.out.println("JAVA OK: " + rs.getString(1));
    }
  }
}
